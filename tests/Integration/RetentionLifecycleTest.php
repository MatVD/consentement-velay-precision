<?php
namespace App\Tests\Integration;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ClientLifecycleEvent;
use App\Entity\ConsentDecision;
use App\Entity\ConsentRequest;
use App\Entity\EvidenceFile;
use App\Entity\EvidencePurgeQueue;
use App\Entity\RetentionAggregate;
use App\Service\ClientLifecycleHistoryWriter;
use App\Service\ClientRetentionProcessor;
use App\Service\ClientRetentionSchedule;
use App\Service\EvidencePurgeExecutor;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class RetentionLifecycleTest extends WebTestCase
{
    private string $oldUrl;
    private string $testUrl;
    private string $evidenceFolder;
    private array $evidencePaths = [];

    protected function setUp(): void
    {
        if (getenv('VELAY_RUN_DB_TESTS') !== '1') {
            self::markTestSkipped('Tests PostgreSQL : VELAY_RUN_DB_TESTS=1.');
        }
        $url = getenv('DATABASE_URL');
        if (!is_string($url) || !preg_match('~/velay\\?~', $url)) {
            self::fail('URL DB non autorisée.');
        }
        $this->oldUrl = $url;
        $this->testUrl = preg_replace('~/velay\\?~', '/velay_phase1_test?', $url, 1);
        $em = $this->manager();
        self::assertSame('velay_phase1_test', $em->getConnection()->getDatabase());
        $schema = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($meta);
        $schema->createSchema($meta);
        $em->getConnection()->close();
        putenv('DATABASE_URL='.$this->testUrl);
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->testUrl;

        $this->evidenceFolder = sys_get_temp_dir().'/velay-retention-'.bin2hex(random_bytes(8));
        mkdir($this->evidenceFolder, 0700);
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        if (isset($this->testUrl)) {
            $em = $this->manager();
            (new SchemaTool($em))->dropSchema($em->getMetadataFactory()->getAllMetadata());
            $em->getConnection()->close();
        }
        if (isset($this->oldUrl)) {
            putenv('DATABASE_URL='.$this->oldUrl);
            $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->oldUrl;
        }
        foreach ($this->evidencePaths as $path) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        if (isset($this->evidenceFolder) && is_dir($this->evidenceFolder)) {
            rmdir($this->evidenceFolder);
        }
    }

    public function testSimulationNeverDeletesPersonalData(): void
    {
        $em = $this->appEm();
        $client = $this->addClient($em, true);
        $processor = $this->processor($em);
        $asOf = new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'));
        self::assertSame(1, $processor->preview($asOf)['due_clients']);
        self::assertSame(1, $em->getRepository(Client::class)->count([]));

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('app:retention:process'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Simulation uniquement', $tester->getDisplay());
        self::assertSame(1, $em->getRepository(Client::class)->count([]));
        self::assertSame(0, $em->getRepository(RetentionAggregate::class)->count([]));
        self::assertNotNull($client->getId());
    }

    public function testApplyRequiresServerGateAndBothConfirmations(): void
    {
        $em = $this->appEm();
        $this->addClient($em, true);
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('app:retention:process'));
        self::assertSame(1, $tester->execute(['--apply' => true]));
        self::assertStringContainsString('Purge refusée', $tester->getDisplay());
        self::assertSame(1, $em->getRepository(Client::class)->count([]));
    }

    public function testAnonymizesOneWithoutPublishingSmallCountAndDeletesPdf(): void
    {
        $em = $this->appEm();
        $client = $this->addClient($em, true);
        $file = $this->addEvidence($em, $client);
        $clientId = $client->getId();
        $filename = $file->getPath();
        $processor = $this->processor($em);
        $at = new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'));

        $done = $processor->process($at);
        self::assertSame(1, $done['anonymized']);
        self::assertSame(0, $done['aggregate_counted']);
        self::assertSame(1, $done['pdfs_removed']);
        self::assertSame(0, $done['pdfs_pending']);
        self::assertFileDoesNotExist($this->evidenceFolder.'/'.$filename);

        $em->clear();
        foreach ([Client::class, ConsentRequest::class, ConsentDecision::class, EvidenceFile::class, AuditEvent::class] as $class) {
            self::assertSame(0, $em->getRepository($class)->count([]), 'Donnée identifiante restante : '.$class);
        }
        self::assertNull($em->getRepository(Client::class)->find($clientId));
        $stat = $em->getRepository(RetentionAggregate::class)->find('2024-05');
        self::assertNotNull($stat);
        self::assertNull($stat->getClientTotal());
        self::assertSame(1, $em->getRepository(ClientLifecycleEvent::class)->count(['eventType' => 'anonymized']));
        self::assertSame(0, $processor->process($at)['anonymized']);

        $later = new \DateTimeImmutable('2028-11-02 12:00:00', new \DateTimeZone('UTC'));
        $end = $processor->process($later);
        self::assertSame(1, $end['statistics_expired']);
        $em->clear();
        self::assertSame(0, $em->getRepository(RetentionAggregate::class)->count([]));
        self::assertSame(1, $em->getRepository(ClientLifecycleEvent::class)->count(['eventType' => 'deleted']));
    }

    public function testFiveClientsCreateOnlyOneAggregateCount(): void
    {
        $em = $this->appEm();
        for ($i = 0; $i < 5; ++$i) {
            $this->addClient($em, true);
        }
        $processor = $this->processor($em);
        $done = $processor->process(new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC')));
        self::assertSame(5, $done['anonymized']);
        self::assertSame(5, $done['aggregate_counted']);
        $em->clear();
        $stat = $em->getRepository(RetentionAggregate::class)->find('2024-05');
        self::assertNotNull($stat);
        self::assertSame(5, $stat->getClientTotal());
        self::assertSame(0, $em->getRepository(Client::class)->count([]));
        self::assertSame(1, $em->getRepository(ClientLifecycleEvent::class)->count(['eventType' => 'anonymized']));
    }

    public function testUnexpiredArchivedClientRemainsUntouched(): void
    {
        $em = $this->appEm();
        $this->addClient($em, false);
        $result = $this->processor($em)->process(
            new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'))
        );
        self::assertSame(0, $result['anonymized']);
        self::assertSame(1, $em->getRepository(Client::class)->count([]));
    }

    public function testInvalidEvidenceNameRollsBackEntireClientPurge(): void
    {
        $em = $this->appEm();
        $client = $this->addClient($em, true);
        $this->addEvidence($em, $client, 'unsafe-file.pdf');
        $this->expectException(\RuntimeException::class);
        try {
            $this->processor($em)->process(
                new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'))
            );
        } finally {
            $em->clear();
            self::assertSame(1, $em->getRepository(Client::class)->count([]));
            self::assertSame(1, $em->getRepository(EvidenceFile::class)->count([]));
            self::assertSame(0, $em->getRepository(RetentionAggregate::class)->count([]));
            self::assertSame(0, $em->getRepository(EvidencePurgeQueue::class)->count([]));
        }
    }

    public function testAlreadyPastThreeYearsDoesNotCreateStatistics(): void
    {
        $em = $this->appEm();
        $client = $this->addClient($em, true);
        $em->getConnection()->executeStatement(
            "UPDATE client SET contract_ended_at = '2020-02-05 00:00:00' WHERE id = :id",
            ['id' => $client->getId()]
        );
        $result = $this->processor($em)->process(
            new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'))
        );
        self::assertSame(1, $result['anonymized']);
        self::assertSame(0, $result['aggregate_counted']);
        self::assertSame(1, $em->getRepository(ClientLifecycleEvent::class)->count(['eventType'=>'deleted']));
        self::assertSame(0, $em->getRepository(RetentionAggregate::class)->count([]));
        $em->clear();
        self::assertSame(0, $em->getRepository(Client::class)->count([]));
    }

    public function testMonthlyHistoryHasNoClientPerEventAndNoPreciseTime(): void
    {
        $em = $this->appEm();
        $history = new ClientLifecycleHistoryWriter();
        $date = new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'));
        $history->record($em->getConnection(), 'anonymized', $date);
        $history->record($em->getConnection(), 'anonymized', $date->modify('+1 day'));
        $em->clear();

        self::assertSame(1, $em->getRepository(ClientLifecycleEvent::class)->count(['eventType'=>'anonymized']));
        $event = $em->getRepository(ClientLifecycleEvent::class)->findOneBy(['eventType'=>'anonymized']);
        self::assertSame('2026-10-01 00:00:00', $event->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testUndeletablePdfRemainsQueuedWithoutRestoringPersonalDatabaseRow(): void
    {
        $em = $this->appEm();
        $client = $this->addClient($em, true);
        $file = $this->addEvidence($em, $client);
        $source = $this->evidenceFolder.'/'.$file->getPath();
        $target = $this->evidenceFolder.'/synthetic-target.pdf';
        rename($source, $target);
        $this->evidencePaths[] = $target;
        symlink($target, $source);

        $result = $this->processor($em)->process(
            new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'))
        );
        self::assertSame(1, $result['anonymized']);
        self::assertSame(0, $result['pdfs_removed']);
        self::assertSame(1, $result['pdfs_pending']);
        $em->clear();
        self::assertSame(0, $em->getRepository(Client::class)->count([]));
        self::assertSame(1, $em->getRepository(EvidencePurgeQueue::class)->count([]));
    }

    private function appEm(): EntityManager
    {
        static::createClient();
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function addClient(EntityManager $em, bool $due): Client
    {
        $client = (new Client())->setName('Commerce privé '.bin2hex(random_bytes(4)))
            ->setEmail('adresse-privee@example.test')->setCodialId('C1234')
            ->setContactFirstName('Anne')->setContactLastName('Martin');
        $em->persist($client);
        $em->flush();
        $date = $due ? '2024-05-01 10:00:00' : '2026-08-01 10:00:00';
        $em->getConnection()->executeStatement(
            "UPDATE client SET lifecycle_stage='archived', contract_ended_at=:date, archived_at=:date WHERE id=:id",
            ['date' => $date, 'id' => $client->getId()]
        );
        return $client;
    }

    private function addEvidence(EntityManager $em, Client $client, ?string $name = null): EvidenceFile
    {
        $request = new ConsentRequest($client, hash('sha256', random_bytes(32)), 'onsite', new \DateTimeImmutable('+1 day'));
        $em->persist($request);
        $em->flush();
        $decision = new ConsentDecision(
            $client, $request, 'Anne', 'Martin', 'Gérante', 'no', 'test-v1', 'Texte fictif',
            new \DateTimeImmutable('2024-05-01', new \DateTimeZone('UTC')), '203.0.113.20', 'synthetic-test'
        );
        $em->persist($decision);
        $em->flush();
        $name ??= 'decision-'.bin2hex(random_bytes(16)).'.pdf';
        $data = '%PDF-1.4 Only synthetic data';
        $path = $this->evidenceFolder.'/'.$name;
        file_put_contents($path, $data);
        $this->evidencePaths[] = $path;
        $file = new EvidenceFile($decision, 'decision_receipt', $name, 'application/pdf', hash('sha256', $data));
        $em->persist($file);
        $em->persist(new AuditEvent($client, $request, 'email_sent', 'admin', ['to'=>'adresse-privee@example.test']));
        $em->flush();
        return $file;
    }

    private function processor(EntityManager $em): ClientRetentionProcessor
    {
        $connection = $em->getConnection();
        return new ClientRetentionProcessor(
            $connection, new ClientLifecycleHistoryWriter(),
            new EvidencePurgeExecutor($connection, $this->evidenceFolder),
            new ClientRetentionSchedule()
        );
    }

    private function manager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $dsn = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($this->testUrl);
        return new EntityManager(DriverManager::getConnection($dsn), $config);
    }
}
