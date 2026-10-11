<?php
namespace App\Tests\Integration;

use App\Controller\Admin\CsvController;
use App\Entity\Client;
use App\Service\ClientCsvImporter;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class CsvImportExportTest extends TestCase
{
    private EntityManager $em;
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        if (getenv('VELAY_RUN_DB_TESTS') !== '1') {
            self::markTestSkipped('Tests PostgreSQL à activer avec VELAY_RUN_DB_TESTS=1.');
        }

        $url = getenv('DATABASE_URL');
        if (!is_string($url) || !preg_match('~/velay\\?~', $url)) {
            self::fail('URL de la base applicative inattendue.');
        }
        $isolatedUrl = preg_replace('~/velay\\?~', '/velay_phase1_test?', $url, 1);
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($isolatedUrl);
        $this->em = new EntityManager(DriverManager::getConnection($params), $config);
        self::assertSame('velay_phase1_test', $this->em->getConnection()->getDatabase());
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (isset($this->em)) {
            $cleaner = $this->em->isOpen() ? $this->em : $this->newEntityManager();
            (new SchemaTool($cleaner))->dropSchema($cleaner->getMetadataFactory()->getAllMetadata());
            $cleaner->getConnection()->close();
            $this->em->getConnection()->close();
        }
    }

    public function testImportPreservesExistingStatusAndReportsInvalidAndDuplicateRows(): void
    {
        $existing = (new Client())->setName('Déjà Client')->setEmail('x@example.fr')->setCodialId('COD-EX');
        $existing->changeStatus(Client::YES, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->em->persist($existing);
        $this->em->flush();
        $existingId = $existing->getId();

        $path = $this->csv("\xEF\xBB\xBFIdentité;Email;Référence Codial;Prénom représentant;Nom représentant;Fonction représentant\n"
            ."Boulangerie Martin;contact@example.fr;COD-01;Anne;Martin;Gérante\n"
            ."BOULANGERIE MARTIN;CONTACT@example.fr;cod-01;;;\n"
            ."Autre;autre@example.fr;cod-ex;;;\n"
            ."Sans email;;COD-02;;;\n"
            ."Invalide;pas-un-email;COD-X;;;\n"
            ."DÉJÀ CLIENT;x@example.fr;;;;\n");

        $report = (new ClientCsvImporter($this->em))->import($path);

        self::assertSame(2, $report->added);
        self::assertSame(3, $report->duplicates);
        self::assertSame(1, $report->rejected);
        self::assertCount(1, $report->errors);
        self::assertSame(6, $report->errors[0]['line']);
        self::assertCount(3, $this->em->getRepository(Client::class)->findAll());
        $this->em->refresh($existing);
        self::assertSame($existingId, $existing->getId());
        self::assertSame(Client::YES, $existing->getStatus());

        $contact = $this->em->getRepository(Client::class)->findOneBy(['codialId' => 'COD-01']);
        self::assertSame('Anne', $contact->getContactFirstName());
        self::assertSame('Martin', $contact->getContactLastName());
        self::assertSame('Gérante', $contact->getContactRole());
        self::assertSame(Client::TODO, $contact->getStatus());
    }

    public function testBatchProcessingImportsMoreThanOneHundredRows(): void
    {
        $csv = "Entreprise;Email;Référence Codial\n";
        for ($i = 0; $i < 130; ++$i) {
            $csv .= "Commerce {$i};boutique{$i}@example.fr;C{$i}\n";
        }

        $report = (new ClientCsvImporter($this->em))->import($this->csv($csv));
        self::assertSame(130, $report->added);
        self::assertSame(0, $report->duplicates);
        self::assertSame(0, $report->rejected);
        self::assertSame(130, $this->em->getRepository(Client::class)->count([]));
    }

    public function testTooManyRowsRejectsEntireImport(): void
    {
        $csv = "Identité\n".str_repeat("Commerce\n", 3001);
        try {
            (new ClientCsvImporter($this->em))->import($this->csv($csv));
            self::fail('La limite de 3 000 lignes doit être appliquée.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('3 000', $exception->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(Client::class)->count([]));
    }

    public function testMissingRequiredColumnRejectsEntireImport(): void
    {
        $file = $this->csv("Email;Référence Codial\ncontact@example.fr;C1\n");
        $this->expectException(\InvalidArgumentException::class);
        (new ClientCsvImporter($this->em))->import($file);
    }

    public function testExportStreamsUtf8BOMAndPreventsSpreadsheetFormulaInjection(): void
    {
        $unsafe = (new Client())->setName('=HYPERLINK("https://example.invalid")');
        $safe = (new Client())->setName('Boulangerie Martin');
        $safe->changeStatus(Client::SENT, new \DateTimeImmutable('2026-10-11 15:00:00', new \DateTimeZone('UTC')));
        $this->em->persist($unsafe);
        $this->em->persist($safe);
        $this->em->flush();

        $response = (new CsvController(new ClientCsvImporter($this->em)))->export($this->em);
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        self::assertStringStartsWith("\xEF\xBB\xBF", $body);
        self::assertStringContainsString("Identité;Date;Statut", $body);
        self::assertStringContainsString("'=HYPERLINK", $body);
        self::assertStringContainsString('Mail envoyé', $body);
        self::assertStringContainsString('11/10/2026 17:00', $body);
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'velay-csv-');
        if ($path === false) {
            throw new \RuntimeException('Échec du fichier CSV temporaire.');
        }
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;
        return $path;
    }

    private function newEntityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $url = preg_replace('~/velay\\?~', '/velay_phase1_test?', getenv('DATABASE_URL'), 1);
        return new EntityManager(DriverManager::getConnection(
            (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url)
        ), $config);
    }
}
