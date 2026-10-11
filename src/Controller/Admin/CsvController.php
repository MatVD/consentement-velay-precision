<?php
namespace App\Controller\Admin;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CsvController extends AbstractController
{
    #[Route('/admin/export.csv', name: 'admin_clients_export', methods: ['GET'])]
    public function export(EntityManagerInterface $em): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($em): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Identité', 'Date', 'Statut'], ';', '"', '');
            $clients = $em->getRepository(Client::class)->findBy([], ['name' => 'ASC']);
            $escape = static function (string $value): string {
                return preg_match('/^[\\s\\x00-\\x1F]*[=+@\\-]/u', $value) ? "'".$value : $value;
            };
            foreach ($clients as $client) {
                fputcsv($handle, [
                    $escape($client->getName()),
                    $client->getStatusAt()?->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y H:i') ?? '',
                    $client->getStatusLabel(),
                ], ';', '"', '');
            }
            fclose($handle);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="velay-consentements.csv"');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    #[AdminRoute(path: '/import', name: 'clients_import', options: ['methods' => ['GET', 'POST']])]
    public function import(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_clients', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $file = $request->files->get('csv');
            if (!$file || !$file->isValid() || $file->getSize() > 1_000_000 || strtolower($file->getClientOriginalExtension()) !== 'csv') {
                $this->addFlash('danger', 'Fichier CSV invalide (1 Mo maximum).');
                return $this->redirectToRoute('admin_clients_import');
            }
            $handle = fopen($file->getPathname(), 'rb');
            if ($handle === false) {
                $this->addFlash('danger', 'Impossible de lire le CSV.');
                return $this->redirectToRoute('admin_clients_import');
            }
            $count = 0;
            $headers = fgetcsv($handle, 4096, ';', '"', '');
            if (!is_array($headers)) { fclose($handle); $this->addFlash('danger', 'CSV vide.'); return $this->redirectToRoute('admin_clients_import'); }
            $headers = array_map(static fn (string $v) => mb_strtolower(trim(preg_replace('/^\\xEF\\xBB\\xBF/', '', $v))), $headers);
            $nameCol = $this->findHeader($headers, ['identité', 'identite', 'nom client', 'client', 'nom']);
            $mailCol = $this->findHeader($headers, ['email', 'e-mail', 'mail']);
            $codeCol = $this->findHeader($headers, ['id codial', 'reference codial', 'référence codial', 'codial']);
            if ($nameCol === null) {
                fclose($handle);
                $this->addFlash('danger', 'La colonne Identité (ou Nom client) est obligatoire.');
                return $this->redirectToRoute('admin_clients_import');
            }
            while (($row = fgetcsv($handle, 4096, ';', '"', '')) !== false && $count < 3000) {
                $name = trim($row[$nameCol] ?? '');
                $email = $mailCol === null ? '' : mb_strtolower(trim($row[$mailCol] ?? ''));
                $codial = $codeCol === null ? '' : trim($row[$codeCol] ?? '');
                if ($name === '' || mb_strlen($name) > 255) { continue; }
                if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180)) { continue; }
                if (mb_strlen($codial) > 100) { continue; }
                $repo = $em->getRepository(Client::class);
                $existing = $codial !== '' ? $repo->findOneBy(['codialId' => $codial]) : null;
                if (!$existing && $email !== '') { $existing = $repo->findOneBy(['name' => $name, 'email' => $email]); }
                if (!$existing && $email === '') { $existing = $repo->findOneBy(['name' => $name, 'email' => null]); }
                if ($existing) { continue; }
                $client = (new Client())->setName($name)->setEmail($email === '' ? null : $email)->setCodialId($codial === '' ? null : $codial);
                $em->persist($client);
                $em->flush();
                $count++;
            }
            fclose($handle);
            $this->addFlash('success', $count.' client(s) ajoutés. Les statuts déjà existants ne sont pas modifiés.');
            return $this->redirectToRoute('admin_clients_import');
        }
        return $this->render('admin/import.html.twig');
    }

    private function findHeader(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $pos = array_search($name, $headers, true);
            if ($pos !== false) { return $pos; }
        }
        return null;
    }
}
