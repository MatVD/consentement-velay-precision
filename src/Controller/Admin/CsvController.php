<?php
namespace App\Controller\Admin;

use App\Entity\Client;
use App\Service\ClientCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class CsvController extends AbstractController
{
    #[Route('/admin/export.csv', name: 'admin_clients_export', methods: ['GET'])]
    public function export(EntityManagerInterface $em): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($em): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Flux de sortie CSV indisponible.');
            }

            try {
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['Identité', 'Date', 'Statut'], ';', '"', '');
                $escape = static function (string $value): string {
                    // Excel may interpret imported text as formulas; prefix dangerous values.
                    return preg_match('/^[\\s\\x00-\\x1F]*[=+@\\-]/u', $value) ? "'".$value : $value;
                };

                // Use a cursor instead of hydrating the entire client table.
                $clients = $em->createQueryBuilder()
                    ->select('c')
                    ->from(Client::class, 'c')
                    ->orderBy('c.name')
                    ->getQuery()->toIterable();
                foreach ($clients as $client) {
                    fputcsv($handle, [
                        $escape($client->getName()),
                        $client->getStatusAt()?->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y H:i') ?? '',
                        $client->getStatusLabel(),
                    ], ';', '"', '');
                    $em->detach($client);
                }
            } finally {
                fclose($handle);
            }
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="velay-consentements.csv"');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[AdminRoute(path: '/import', name: 'clients_import', options: ['methods' => ['GET', 'POST']])]
    public function import(Request $request, ClientCsvImporter $importer): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_clients', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $file = $request->files->get('csv');
            if (!$file instanceof UploadedFile || !$file->isValid()
                || $file->getSize() > ClientCsvImporter::MAX_FILE_BYTES
                || strtolower($file->getClientOriginalExtension()) !== 'csv'
            ) {
                $this->addFlash('danger', 'Fichier CSV invalide (extension .csv, 1 Mo maximum).');
                return $this->redirectToRoute('admin_clients_import');
            }

            try {
                $report = $importer->import($file->getPathname());
                $this->addFlash('import_result', $report->toArray());
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }

            return $this->redirectToRoute('admin_clients_import');
        }

        return $this->render('admin/import.html.twig');
    }
}
