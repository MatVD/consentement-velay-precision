<?php
namespace App\Tests\Integration;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentDecision;
use App\Entity\ConsentRequest;
use App\Entity\EvidenceFile;
use App\Entity\User;
use App\Service\EvidenceArchive;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ProtectedProofRoutesTest extends WebTestCase
{
    private string $previousUrl;
    private string $isolatedUrl;
    private string $tempEvidenceDirectory;

    protected function setUp(): void
    {
        if (getenv('VELAY_RUN_DB_TESTS') !== '1') {
            self::markTestSkipped('Activer les tests PostgreSQL avec VELAY_RUN_DB_TESTS=1.');
        }

        $url = getenv('DATABASE_URL');
        if (!is_string($url) || !preg_match('~/velay\\?~', $url)) {
            self::fail('URL PostgreSQL inattendue : refus de toucher à la base applicative.');
        }
        $this->previousUrl = $url;
        $this->isolatedUrl = preg_replace('~/velay\\?~', '/velay_phase1_test?', $url, 1);
        $database = $this->manager();
        self::assertSame('velay_phase1_test', $database->getConnection()->getDatabase());
        $schema = new SchemaTool($database);
        $metadata = $database->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $database->getConnection()->close();

        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->isolatedUrl;
        putenv('DATABASE_URL='.$this->isolatedUrl);
        $this->tempEvidenceDirectory = sys_get_temp_dir().'/velay-route-proof-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->tempEvidenceDirectory, 0700));
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        if (isset($this->previousUrl)) {
            putenv('DATABASE_URL='.$this->previousUrl);
            $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->previousUrl;
            $db = $this->manager();
            (new SchemaTool($db))->dropSchema($db->getMetadataFactory()->getAllMetadata());
            $db->getConnection()->close();
        }

        if (isset($this->tempEvidenceDirectory) && is_dir($this->tempEvidenceDirectory)) {
            foreach (glob($this->tempEvidenceDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->tempEvidenceDirectory);
        }
    }

    public function testAnonymousVisitorCannotDownloadEvidence(): void
    {
        $browser = static::createClient();
        $browser->request('GET', '/admin/proofs/1/download');
        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedAdministratorGets404ForUnknownEvidence(): void
    {
        $browser = static::createClient();
        $this->authenticate($browser);
        $browser->request('GET', '/admin/proofs/99999/download');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEvidenceDownloadUsesPrivateHashCheckedFile(): void
    {
        $browser = static::createClient();
        $this->authenticate($browser);
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $client = (new Client())->setName('Commerce de test');
        $em->persist($client);
        $em->flush();

        $invitation = new ConsentRequest($client, str_repeat('b', 64), 'onsite', new \DateTimeImmutable('+1 day'));
        $em->persist($invitation);
        $em->flush();

        $decision = new ConsentDecision(
            $client, $invitation, 'Alice', 'Martin', 'Gérante',
            'yes', 'test-v1', 'Texte de test',
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')), null, null
        );
        $em->persist($decision);
        $em->flush();

        $pdf = "%PDF-1.4\nIntegration fixture\n";
        $name = 'test-receipt.pdf';
        file_put_contents($this->tempEvidenceDirectory.'/'.$name, $pdf);
        $evidence = new EvidenceFile($decision, 'decision_receipt', $name, 'application/pdf', hash('sha256', $pdf));
        $em->persist($evidence);
        $em->flush();

        static::getContainer()->set(
            EvidenceArchive::class,
            new EvidenceArchive(new Environment(new ArrayLoader()), $this->tempEvidenceDirectory)
        );

        $route = '/admin/proofs/'.$evidence->getId().'/download';
        $browser->request('GET', $route);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment;', $browser->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $browser->getResponse()->headers->get('Cache-Control'));
        $reads = $em->getRepository(AuditEvent::class)->findBy(['type' => 'evidence_downloaded']);
        self::assertCount(1, $reads);
        self::assertSame($evidence->getId(), $reads[0]->getDetails()['evidence_id']);

        file_put_contents($this->tempEvidenceDirectory.'/'.$name, "%PDF-1.4\nAltered\n");
        $browser->request('GET', $route);
        self::assertResponseStatusCodeSame(404);
        self::assertCount(1, $em->getRepository(AuditEvent::class)->findBy(['type' => 'evidence_downloaded']));
    }

    private function authenticate(\Symfony\Bundle\FrameworkBundle\KernelBrowser $browser): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = (new User())->setEmail('test-admin@example.test')->setPassword('test-bypassed-via-loginUser');
        $em->persist($user);
        $em->flush();
        $browser->loginUser($user);
    }

    private function manager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig(
            [dirname(__DIR__, 2).'/src/Entity'], true
        );
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $parameters = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($this->isolatedUrl);
        return new EntityManager(DriverManager::getConnection($parameters), $config);
    }
}
