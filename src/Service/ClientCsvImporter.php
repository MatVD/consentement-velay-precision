<?php
namespace App\Service;

use App\Dto\ClientImportReport;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;

final class ClientCsvImporter
{
    public const MAX_FILE_BYTES = 1_000_000;
    public const MAX_RECORDS = 3000;
    private const BATCH_SIZE = 100;
    private const MAX_ERRORS_IN_REPORT = 40;

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function import(string $path): ClientImportReport
    {
        $size = is_file($path) ? filesize($path) : false;
        if ($size === false || $size === 0 || $size > self::MAX_FILE_BYTES || !is_readable($path)) {
            throw new \InvalidArgumentException('Fichier CSV invalide (1 Mo maximum).');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \InvalidArgumentException('Impossible de lire le fichier CSV.');
        }

        $prepared = [];
        $errors = [];
        $rejected = 0;
        try {
            $headers = fgetcsv($stream, 0, ';', '"', '');
            if (!is_array($headers) || !mb_check_encoding(implode(';', $headers), 'UTF-8')) {
                throw new \InvalidArgumentException('En-tête CSV vide ou non encodé en UTF-8.');
            }
            $headers = array_map(
                static fn (?string $value): string => mb_strtolower(trim(preg_replace('/^\\xEF\\xBB\\xBF/', '', $value ?? ''))),
                $headers
            );
            if (count(array_unique($headers)) !== count($headers)) {
                throw new \InvalidArgumentException('Le CSV comporte des en-têtes dupliqués.');
            }
            $columns = [
                'name' => $this->findHeader($headers, ['identité', 'identite', 'nom client', 'client', 'nom', 'entreprise']),
                'email' => $this->findHeader($headers, ['email', 'e-mail', 'mail']),
                'codial' => $this->findHeader($headers, ['id codial', 'reference codial', 'référence codial', 'codial']),
                'firstName' => $this->findHeader($headers, ['prénom représentant', 'prenom representant', 'prénom du représentant', 'prenom du representant', 'prénom contact', 'prenom contact']),
                'lastName' => $this->findHeader($headers, ['nom représentant', 'nom representant', 'nom du représentant', 'nom du representant', 'nom contact']),
                'role' => $this->findHeader($headers, ['fonction représentant', 'fonction representant', 'fonction du représentant', 'fonction du representant', 'fonction', 'qualité', 'qualite']),
            ];
            if ($columns['name'] === null) {
                throw new \InvalidArgumentException('La colonne « Identité » ou « Entreprise » est obligatoire.');
            }

            $records = 0;
            $line = 1;
            while (($record = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                ++$line;
                if ($record === [null] || (count($record) === 1 && trim((string) $record[0]) === '')) {
                    continue;
                }
                if (++$records > self::MAX_RECORDS) {
                    throw new \InvalidArgumentException('Le CSV dépasse la limite de 3 000 lignes de données. Aucun client n’a été importé.');
                }
                if (count($record) > count($headers) || !mb_check_encoding(implode(';', $record), 'UTF-8')) {
                    ++$rejected;
                    $this->recordError($errors, $line, 'Nombre de colonnes incorrect ou encodage non UTF-8.');
                    continue;
                }
                $value = static function (string $key) use ($record, $columns): string {
                    $position = $columns[$key];
                    return $position === null ? '' : trim((string) ($record[$position] ?? ''));
                };
                $name = $value('name');
                $email = mb_strtolower($value('email'));
                $codial = $value('codial');
                $firstName = $value('firstName');
                $lastName = $value('lastName');
                $role = $value('role');

                $problem = match (true) {
                    $name === '' || mb_strlen($name) > 255 => 'Entreprise / client manquant ou trop long (255 caractères maximum).',
                    $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) => 'Email invalide ou trop long.',
                    mb_strlen($codial) > 100 => 'Référence Codial trop longue (100 caractères maximum).',
                    mb_strlen($firstName) > 120 || mb_strlen($lastName) > 120 || mb_strlen($role) > 120 => 'Information de représentant trop longue (120 caractères maximum).',
                    default => null,
                };
                if ($problem !== null) {
                    ++$rejected;
                    $this->recordError($errors, $line, $problem);
                    continue;
                }
                $prepared[] = compact('name', 'email', 'codial', 'firstName', 'lastName', 'role');
            }
        } finally {
            fclose($stream);
        }

        // One import is atomic. The advisory transaction lock serializes concurrent
        // CSV imports before checking existing clients; the application uses PostgreSQL.
        return $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($prepared, $rejected, $errors): ClientImportReport {
            $em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(49071102)');

            $existingCodes = [];
            $existingIdentities = [];
            $existing = $em->createQueryBuilder()
                ->select('c.name AS name, c.email AS email, c.codialId AS codialId')
                ->from(Client::class, 'c')
                ->getQuery()->toIterable();
            foreach ($existing as $record) {
                $existingIdentities[$this->identityKey($record['name'], $record['email'] ?? '')] = true;
                if (!empty($record['codialId'])) {
                    $existingCodes[mb_strtolower(trim($record['codialId']))] = true;
                }
            }

            $added = 0;
            $duplicates = 0;
            $batch = [];
            foreach ($prepared as $data) {
                $identity = $this->identityKey($data['name'], $data['email']);
                $codialKey = mb_strtolower($data['codial']);
                if (isset($existingIdentities[$identity]) || ($codialKey !== '' && isset($existingCodes[$codialKey]))) {
                    ++$duplicates;
                    continue;
                }

                $client = (new Client())
                    ->setName($data['name'])
                    ->setEmail($data['email'])
                    ->setCodialId($data['codial'])
                    ->setContactFirstName($data['firstName'])
                    ->setContactLastName($data['lastName'])
                    ->setContactRole($data['role']);
                $em->persist($client);
                $batch[] = $client;
                $existingIdentities[$identity] = true;
                if ($codialKey !== '') {
                    $existingCodes[$codialKey] = true;
                }

                if (++$added % self::BATCH_SIZE === 0) {
                    $em->flush();
                    // Only detach imported clients. Never clear the authenticated
                    // user or another entity managed during the admin request.
                    foreach ($batch as $inserted) {
                        $em->detach($inserted);
                    }
                    $batch = [];
                }
            }

            return new ClientImportReport($added, $duplicates, $rejected, $errors);
        });
    }

    private function identityKey(string $name, string $email): string
    {
        return mb_strtolower(trim($name))."\0".mb_strtolower(trim($email));
    }

    private function findHeader(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $position = array_search($name, $headers, true);
            if ($position !== false) {
                return $position;
            }
        }
        return null;
    }

    /** @param list<array{line:int,reason:string}> $errors */
    private function recordError(array &$errors, int $line, string $reason): void
    {
        if (count($errors) < self::MAX_ERRORS_IN_REPORT) {
            $errors[] = ['line' => $line, 'reason' => $reason];
        }
    }
}
