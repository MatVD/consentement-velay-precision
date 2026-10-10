<?php
namespace App\Service;

use App\Entity\ConsentDecision;
use App\Entity\EvidenceFile;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class EvidenceArchive
{
    public function __construct(private readonly Environment $twig, private readonly string $evidenceDirectory) {}

    public function createReceipt(ConsentDecision $decision): EvidenceFile
    {
        if (!is_dir($this->evidenceDirectory) && !mkdir($this->evidenceDirectory, 0700, true) && !is_dir($this->evidenceDirectory)) {
            throw new \RuntimeException('Impossible de créer le dossier des preuves.');
        }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('pdf/receipt.html.twig', ['decision' => $decision]), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $bytes = $pdf->output();
        $basename = 'decision-'.bin2hex(random_bytes(16)).'.pdf';
        $fullPath = rtrim($this->evidenceDirectory, '/').'/'.$basename;
        if (file_put_contents($fullPath, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d’enregistrer le justificatif.');
        }
        chmod($fullPath, 0600);
        return new EvidenceFile($decision, 'decision_receipt', $basename, 'application/pdf', hash('sha256', $bytes));
    }

    public function absolutePath(EvidenceFile $file): string
    {
        $base = basename($file->getPath());
        if ($base !== $file->getPath()) {
            throw new \RuntimeException('Chemin de preuve invalide.');
        }
        return rtrim($this->evidenceDirectory, '/').'/'.$base;
    }

    public function remove(EvidenceFile $file): void
    {
        $path = $this->absolutePath($file);
        if (is_file($path)) { unlink($path); }
    }
}
