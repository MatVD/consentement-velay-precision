<?php
namespace App\Tests\Integration;

use App\Dto\ConsentSubmission;
use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentDecision;
use App\Entity\ConsentRequest;
use App\Service\ConsentRecorder;
use App\Service\EvidenceArchive;
use App\Service\InvitationService;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Optional real PostgreSQL tests. Provision the **isolated** database once:
 *
 * docker compose -f compose.yaml -f compose.dev.yaml exec -T database createdb -U velay velay_phase1_test
 * docker compose -f compose.yaml -f compose.dev.yaml exec -T -e VELAY_RUN_DB_TESTS=1 app php vendor/bin/simple-phpunit tests/Integration
 *
 * A guard below refuses to run against the application's 'velay' database.
 */
final class InvitationPersistenceTest extends TestCase
{
    private string $testUrl;
    private string $receiptsDirectory;
    private EntityManager $first;
    private EntityManager $second;

    protected function setUp(): void
    {
        if (getenv('VELAY_RUN_DB_TESTS') !== '1') {
            self::markTestSkipped('Tests PostgreSQL activés explicitement avec VELAY_RUN_DB_TESTS=1.');
        }
        $appUrl = getenv('DATABASE_URL');
        if (!is_string($appUrl) || !preg_match('~/velay\\?~', $appUrl)) {
            self::fail('URL de la base applicative inattendue : test interrompu par sécurité.');
        }
        $this->testUrl = preg_replace('~/velay\\?~', '/velay_phase1_test?', $appUrl, 1);
        $this->first = $this->manager();
        $this->second = $this->manager();

        // Never drop/create schemas on the real application database.
        self::assertSame('velay_phase1_test', $this->first->getConnection()->getDatabase());
        self::assertSame('velay_phase1_test', $this->second->getConnection()->getDatabase());
        $metadata = $this->first->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->first);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->receiptsDirectory = sys_get_temp_dir().'/velay-phase1-integration-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (isset($this->first)) {
            // Use a fresh manager even when wrapInTransaction closed an EM on an expected failure.
            $cleanup = $this->manager();
            (new SchemaTool($cleanup))->dropSchema($cleanup->getMetadataFactory()->getAllMetadata());
            $cleanup->getConnection()->close();
            $this->first->getConnection()->close();
            $this->second->getConnection()->close();
        }
        if (isset($this->receiptsDirectory) && is_dir($this->receiptsDirectory)) {
            foreach (glob($this->receiptsDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->receiptsDirectory);
        }
    }

    public function testStaleOperatorCannotIssueInvitationAfterDecision(): void
    {
        $client = (new Client())->setName('Client de recette');
        $this->first->persist($client);
        $this->first->flush();

        $staleClient = $this->second->find(Client::class, $client->getId());
        $client->changeStatus(Client::YES, new \DateTimeImmutable());
        $this->first->flush();

        $service = new InvitationService(
            $this->second, $this->createMock(MailerInterface::class),
            'https://consentement.example.test', 'contact@example.test'
        );

        try {
            $service->create($staleClient, 'onsite');
            self::fail('La création devait refuser le statut désormais définitif.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('définitive', $exception->getMessage());
        }

        self::assertCount(0, $this->first->getRepository(ConsentRequest::class)->findAll());
    }

    public function testDecisionRecordedDuringSmtpIsNotOverwritten(): void
    {
        $client = (new Client())->setName('Client de recette')->setEmail('contact@example.test');
        $this->first->persist($client);
        $this->first->flush();
        $clientId = $client->getId();

        $archive = new EvidenceArchive(
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            $this->receiptsDirectory
        );
        $recipientManager = $this->second;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            static function () use ($recipientManager, $clientId, $archive): void {
                $respondent = $recipientManager->find(Client::class, $clientId);
                $submission = new ConsentSubmission();
                $submission->firstName = 'Alice';
                $submission->lastName = 'Martin';
                $submission->signatoryRole = 'Gérante';
                $submission->choice = 'no';
                (new ConsentRecorder($recipientManager, $archive))
                    ->recordAssisted($respondent, $submission, Request::create('/test'), 'test-operator');
            }
        );

        (new InvitationService($this->first, $mailer, 'https://consentement.example.test', 'contact@example.test'))
            ->sendEmail($client);

        $this->first->refresh($client);
        self::assertSame(Client::NO, $client->getStatus());
        self::assertCount(1, $this->first->getRepository(ConsentDecision::class)->findAll());
        $events = $this->first->getRepository(AuditEvent::class)->findBy(['type' => 'email_sent']);
        self::assertCount(1, $events);
        self::assertFalse($events[0]->getDetails()['status_updated']);
        self::assertCount(1, $this->first->getRepository(ConsentRequest::class)->findBy(['channel' => 'email']));
    }

    public function testPublicDecisionConsumesTokenAndArchivesReceipt(): void
    {
        $client = (new Client())->setName('Commerce public');
        $this->first->persist($client);
        $this->first->flush();

        [$invitation, $link] = (new InvitationService(
            $this->first, $this->createMock(MailerInterface::class),
            'https://consentement.example.test', 'contact@example.test'
        ))->create($client, 'onsite');
        $token = substr($link, strrpos($link, '/') + 1);
        self::assertSame(64, strlen($token));

        $submission = new ConsentSubmission();
        $submission->firstName = 'Alice';
        $submission->lastName = 'Martin';
        $submission->signatoryRole = 'Gérante';
        $submission->choice = 'yes';

        $archive = new EvidenceArchive(
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            $this->receiptsDirectory
        );
        $recorder = new ConsentRecorder($this->first, $archive);
        $decision = $recorder->record($token, $submission, Request::create('/c/'.$token));

        self::assertSame(Client::YES, $client->getStatus());
        self::assertNotNull($invitation->getUsedAt());
        self::assertSame('yes', $decision->getChoice());
        self::assertCount(1, $this->first->getRepository(ConsentDecision::class)->findAll());
        self::assertSame(1, count(glob($this->receiptsDirectory.'/*.pdf') ?: []));

        try {
            $recorder->record($token, $submission, Request::create('/c/'.$token));
            self::fail('Le même lien ne doit jamais créer une seconde réponse.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('disponible', $exception->getMessage());
        }
    }

    private function manager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig(
            [dirname(__DIR__, 2).'/src/Entity'], true
        );
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $parameters = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($this->testUrl);
        return new EntityManager(DriverManager::getConnection($parameters), $config);
    }
}
