<?php
namespace App\Tests\Integration;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityAccessTest extends WebTestCase
{
    private string $originalUrl;
    private string $testUrl;

    protected function setUp(): void
    {
        if (getenv('VELAY_RUN_DB_TESTS') !== '1') {
            self::markTestSkipped('Tests PostgreSQL : VELAY_RUN_DB_TESTS=1.');
        }
        $url = getenv('DATABASE_URL');
        if (!is_string($url) || !preg_match('~/velay\\?~', $url)) {
            self::fail('URL de base imprévue : protection du schéma applicatif.');
        }
        $this->originalUrl = $url;
        $this->testUrl = preg_replace('~/velay\\?~', '/velay_phase1_test?', $url, 1);
        $em = $this->manager();
        self::assertSame('velay_phase1_test', $em->getConnection()->getDatabase());
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $em->getConnection()->close();

        putenv('DATABASE_URL='.$this->testUrl);
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->testUrl;
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        if (isset($this->testUrl)) {
            $em = $this->manager();
            (new SchemaTool($em))->dropSchema($em->getMetadataFactory()->getAllMetadata());
            $em->getConnection()->close();
        }
        if (isset($this->originalUrl)) {
            putenv('DATABASE_URL='.$this->originalUrl);
            $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->originalUrl;
        }
    }

    public function testRolesAreNotSilentlyElevatedToAdmin(): void
    {
        $auditor = (new User())->setRoles(['ROLE_AUDITOR']);
        self::assertContains('ROLE_AUDITOR', $auditor->getRoles());
        self::assertNotContains('ROLE_ADMIN', $auditor->getRoles());
        self::assertContains('ROLE_ADMIN', (new User())->getRoles());
        $this->expectException(\InvalidArgumentException::class);
        (new User())->setRoles(['ROLE_SUPER_ADMIN']);
    }

    public function testAuditorCanReadButCannotModifyOrExport(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = (new Client())->setName('Client audité')->setEmail('contact@example.test');
        $em->persist($client);
        $em->flush();
        $auditor = $this->saveUser($em, 'auditeur@example.test', ['ROLE_AUDITOR']);
        $browser->loginUser($auditor);

        $browser->request('GET', '/admin/client');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/admin/clients/'.$client->getId().'/actions');
        self::assertResponseIsSuccessful();

        foreach ([
            '/admin/client/new',
            '/admin/client/'.$client->getId().'/edit',
            '/admin/import',
            '/admin/export.csv',
            '/admin/clients/'.$client->getId().'/assisted',
        ] as $route) {
            $browser->request('GET', $route);
            self::assertResponseStatusCodeSame(403, 'Accès refusé attendu : '.$route);
        }

        foreach ([
            '/admin/clients/'.$client->getId().'/onsite',
            '/admin/clients/'.$client->getId().'/email',
        ] as $route) {
            $browser->request('POST', $route);
            self::assertResponseStatusCodeSame(403, 'Action interdite : '.$route);
        }
    }

    public function testOperatorCannotImportExportButCanManageClients(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $operator = $this->saveUser($em, 'operateur@example.test', ['ROLE_OPERATOR']);
        $browser->loginUser($operator);

        $browser->request('GET', '/admin/client/new');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/admin/import');
        self::assertResponseStatusCodeSame(403);
        $browser->request('GET', '/admin/export.csv');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLegacyAdminKeepsPermissions(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $this->saveUser($em, 'admin@example.test', ['ROLE_ADMIN']);
        $browser->loginUser($user);

        $browser->request('GET', '/admin/client/new');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/admin/import');
        self::assertResponseIsSuccessful();
    }

    private function saveUser(EntityManager $em, string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setPassword('fixture')->setRoles($roles);
        $em->persist($user);
        $em->flush();
        return $user;
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
