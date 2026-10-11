<?php
namespace App\Tests;

use App\Controller\Admin\ClientActionsController;
use App\Entity\Client;
use App\Entity\ConsentDecision;
use App\Entity\ConsentRequest;
use App\Entity\EvidenceFile;
use App\Service\EvidenceArchive;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class EvidenceDownloadTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/velay-proof-test-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testDownloadsUntamperedPrivateFile(): void
    {
        $pdf = "%PDF-1.4\nTest private receipt\n";
        file_put_contents($this->directory.'/receipt.pdf', $pdf);
        $response = (new ClientActionsController())->download(
            $this->file('receipt.pdf', hash('sha256', $pdf)),
            $this->archive()
        );

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringContainsString('private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function testDoesNotDownloadAlteredFile(): void
    {
        file_put_contents($this->directory.'/receipt.pdf', "%PDF-1.4\nModified\n");
        $this->expectException(NotFoundHttpException::class);

        (new ClientActionsController())->download(
            $this->file('receipt.pdf', hash('sha256', "%PDF-1.4\nOriginal\n")),
            $this->archive()
        );
    }

    public function testDoesNotDownloadMissingFile(): void
    {
        $this->expectException(NotFoundHttpException::class);
        (new ClientActionsController())->download(
            $this->file('missing.pdf', str_repeat('a', 64)),
            $this->archive()
        );
    }

    private function archive(): EvidenceArchive
    {
        return new EvidenceArchive(new Environment(new ArrayLoader()), $this->directory);
    }

    private function file(string $name, string $hash): EvidenceFile
    {
        $client = (new Client())->setName('Société de test');
        $request = new ConsentRequest($client, str_repeat('a', 64), 'onsite', new \DateTimeImmutable('+1 day'));
        $decision = new ConsentDecision(
            $client, $request, 'Alice', 'Martin', 'Gérante', 'yes', 'test-v1',
            'Texte de recette', new \DateTimeImmutable('now', new \DateTimeZone('UTC')), null, null
        );
        return new EvidenceFile($decision, 'decision_receipt', $name, 'application/pdf', $hash);
    }
}
