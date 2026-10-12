<?php
namespace App\Tests\Integration;

use App\Entity\Client;
use App\Entity\ConsentRequest;
use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminWorkflowTest extends WebTestCase
{
    private string $oldUrl;
    private string $testUrl;
    private array $files = [];

    protected function setUp(): void
    {
        if (getenv('VELAY_RUN_DB_TESTS') !== '1') {
            self::markTestSkipped('Tests PostgreSQL à activer avec VELAY_RUN_DB_TESTS=1.');
        }
        $url = getenv('DATABASE_URL');
        if (!is_string($url) || !preg_match('~/velay\\?~', $url)) {
            self::fail('URL de la base applicative inattendue.');
        }
        $this->oldUrl = $url;
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
        if (isset($this->oldUrl)) {
            putenv('DATABASE_URL='.$this->oldUrl);
            $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $this->oldUrl;
        }
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testFrenchIndexExposesFiltersAndClientQuickActions(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->persist((new Client())->setName('Boulangerie Martin')->setEmail('contact@example.fr'));
        $em->flush();

        $crawler = $browser->request('GET', '/admin/client');
        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertStringContainsString('Filtres', $crawler->text());
        self::assertStringContainsString('Ajouter un client', $crawler->text());
        self::assertSelectorExists('td[data-column="quickActions"] form[action$="/email"]');
        self::assertSelectorExists('td[data-column="quickActions"] form[action$="/onsite"]');
        self::assertSelectorExists('td[data-column="quickActions"] a[href$="/assisted"]');
        self::assertSelectorExists('link[rel="stylesheet"][href$="/assets/admin.css"]');

        $newClientPage = $browser->request('GET', '/admin/client/new');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Ajouter un client', $newClientPage->text());
        self::assertStringContainsString('Représentant (facultatif)', $newClientPage->text());
    }

    public function testClientEditFieldsAndAssistedPrefillUseExplicitRepresentative(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = (new Client())->setName('Boulangerie Martin')
            ->setContactFirstName('Anne')->setContactLastName('Martin')
            ->setContactRole('Gérante');
        $em->persist($client);
        $em->flush();

        $crawler = $browser->request('GET', '/admin/client/'.$client->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name$="[contactFirstName]"][value="Anne"]'));
        self::assertCount(1, $crawler->filter('input[name$="[contactLastName]"][value="Martin"]'));
        self::assertCount(1, $crawler->filter('input[name$="[contactRole]"][value="Gérante"]'));

        $crawler = $browser->request('GET', '/admin/clients/'.$client->getId().'/assisted');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="consent[firstName]"][value="Anne"]'));
        self::assertCount(1, $crawler->filter('input[name="consent[lastName]"][value="Martin"]'));
        self::assertCount(1, $crawler->filter('input[name="consent[signatoryRole]"][value="Gérante"]'));
        self::assertCount(0, $crawler->filter('input[name="consent[choice]"]:checked'));
        self::assertStringContainsString('Votre choix concernant la signature électronique', $crawler->text());
        self::assertStringNotContainsString('Texte présenté au client', $crawler->text());
        self::assertStringNotContainsString('2026-10-10-v1', $crawler->text());
        self::assertStringNotContainsString('technicien', $crawler->text());
        self::assertCount(0, $crawler->filter('input[name$="[assistedConfirmation]"]'));
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href$="/assets/app.css"]'));
    }

    public function testAssistedConsentRequiresSeparateTechnicianConfirmation(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = (new Client())->setName('Commerce de démonstration');
        $em->persist($client);
        $em->flush();

        $page = $browser->request('GET', '/admin/clients/'.$client->getId().'/assisted');
        self::assertResponseIsSuccessful();
        $form = $page->selectButton('Confirmer mon choix')->form([
            'consent[firstName]' => 'Alice',
            'consent[lastName]' => 'Martin',
            'consent[signatoryRole]' => 'Gérante',
            'consent[choice]' => 'yes',
        ]);
        $browser->submit($form);
        self::assertResponseRedirects('/admin/clients/'.$client->getId().'/assisted/confirm');
        self::assertSame(Client::TODO, $client->getStatus());

        $confirmation = $browser->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Confirmation du technicien', $confirmation->text());
        self::assertStringContainsString('Alice Martin', $confirmation->text());
        self::assertStringContainsString('Oui, j’accepte', $confirmation->text());
        self::assertCount(1, $confirmation->filter('input[type="checkbox"]'));
        self::assertCount(0, $confirmation->filter('input[name="consent[firstName]"]'));
    }

    public function testImportFormShowsReportAfterPostingCsv(): void
    {
        $browser = $this->browserWithAdmin();
        $browser->catchExceptions(false);
        $crawler = $browser->request('GET', '/admin/import');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Prénom représentant', $crawler->text());
        $csrf = $crawler->filter('input[name="_token"]')->attr('value');

        $path = tempnam(sys_get_temp_dir(), 'velay-admin-csv-');
        $this->files[] = $path;
        file_put_contents($path, "Identité;Email;Prénom représentant;Nom représentant\nBoulangerie;contact@example.fr;Anne;Martin\nLigne en erreur;invalid-email;;\n");

        $browser->request('POST', '/admin/import', ['_token' => $csrf], [
            'csv' => new UploadedFile($path, 'clients.csv', 'text/csv', null, true),
        ]);
        self::assertResponseRedirects('/admin/import');
        $crawler = $browser->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Import terminé', $crawler->text());
        self::assertStringContainsString('1 client(s) ajouté(s)', $crawler->text());
        self::assertStringContainsString('Ligne 3', $crawler->text());

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = $em->getRepository(Client::class)->findOneBy(['name' => 'Boulangerie']);
        self::assertSame('Anne', $client->getContactFirstName());
    }

    public function testStatusFilterOnlyShowsMatchingClients(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $pending = (new Client())->setName('Commerce en attente');
        $sent = (new Client())->setName('Commerce déjà invité');
        $sent->changeStatus(Client::SENT, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $em->persist($pending);
        $em->persist($sent);
        $em->flush();

        $crawler = $browser->request('GET', '/admin/client', [
            'filters' => ['status' => ['comparison' => '=', 'value' => Client::TODO]],
        ]);
        self::assertResponseIsSuccessful();

        $names = $crawler->filter('td[data-column="name"]')->each(
            static fn ($node): string => trim($node->text())
        );
        self::assertContains('Commerce en attente', $names);
        self::assertNotContains('Commerce déjà invité', $names);
    }

    public function testOnsiteQuickActionCreatesOnlyAnInvitation(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = (new Client())->setName('Commerce présentiel');
        $em->persist($client);
        $em->flush();

        $crawler = $browser->request('GET', '/admin/client');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/onsite"]')->form();
        $browser->submit($form);
        self::assertResponseRedirects('/admin/client');
        $crawler = $browser->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Lien présentiel généré', $crawler->text());
        $closeButton = $crawler->filter('.client-link-flash > button.btn-close');
        self::assertCount(1, $closeButton);
        self::assertSame('button', $closeButton->attr('type'));
        self::assertSame('Fermer le message de lien présentiel', $closeButton->attr('aria-label'));
        self::assertSame('alert', $closeButton->attr('data-bs-dismiss'));
        $storedClient = $em->getRepository(Client::class)->find($client->getId());
        self::assertSame(Client::TODO, $storedClient->getStatus());
        self::assertSame(1, $em->getRepository(\App\Entity\ConsentRequest::class)->count([]));

        $crawler = $browser->request('GET', '/admin/clients/'.$client->getId().'/actions');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Lien créé', $crawler->text());
        self::assertStringContainsString('Collaborateur', $crawler->text());
    }

    public function testResponsiveAssetsAndViewportAcrossAdminScreens(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = (new Client())->setName('Commerce avec un nom assez long pour le mobile')
            ->setEmail('contact@example.fr');
        $em->persist($client);
        $em->flush();

        foreach ([
            '/admin/client',
            '/admin/client/new',
            '/admin/client/'.$client->getId().'/edit',
            '/admin/clients/'.$client->getId().'/actions',
            '/admin/import',
        ] as $route) {
            $crawler = $browser->request('GET', $route);

            self::assertResponseIsSuccessful($route);
            self::assertCount(
                1,
                $crawler->filter('meta[name="viewport"][content="width=device-width, initial-scale=1"]'),
                'Viewport absent : '.$route
            );
            self::assertCount(
                1,
                $crawler->filter('link[rel="stylesheet"][href$="/assets/admin.css"]'),
                'CSS responsive absent : '.$route
            );
            self::assertSame('fr', $crawler->filter('html')->attr('lang'), $route);
        }

        $assisted = $browser->request('GET', '/admin/clients/'.$client->getId().'/assisted');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $assisted->filter('link[rel="stylesheet"][href$="/assets/app.css"]'));

        $crawler = $browser->request('GET', '/admin/client');
        self::assertResponseIsSuccessful();
        self::assertCount(
            1,
            $crawler->filter('script[src$="/assets/client-mobile-accordion.js"]'),
            'Le comportement de dépliage des fiches mobiles doit être chargé uniquement sur la liste Clients.'
        );
        self::assertCount(1, $crawler->filter('tbody td[data-column="status"]'));
        self::assertCount(
            3,
            $crawler->filter('td[data-column="quickActions"] .client-action-label'),
            'Les actions clients doivent porter trois vrais libellés, visibles sur mobile.'
        );
    }

    public function testPublicConsentFormHasFluidLayoutAndChoiceControls(): void
    {
        $browser = $this->browserWithAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client = (new Client())->setName('Boulangerie de démonstration');
        $em->persist($client);
        $em->flush();

        $token = bin2hex(random_bytes(32));
        $invitation = new ConsentRequest(
            $client,
            hash('sha256', $token),
            'onsite',
            new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC'))
        );
        $em->persist($invitation);
        $em->flush();

        $crawler = $browser->request('GET', '/c/'.$token);
        self::assertResponseIsSuccessful();
        self::assertCount(
            1,
            $crawler->filter('meta[name="viewport"][content="width=device-width, initial-scale=1"]')
        );
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href$="/assets/app.css"]'));
        self::assertCount(1, $crawler->filter('.consent-card .field-grid'));
        self::assertCount(1, $crawler->filter('.choice-area'));
        self::assertCount(2, $crawler->filter('.choice-area input[type="radio"]'));
    }

    private function browserWithAdmin(): KernelBrowser
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = (new User())->setEmail('admin-workflow@example.test')->setPassword('test-bypass');
        $em->persist($user);
        $em->flush();
        $browser->loginUser($user);
        return $browser;
    }

    private function manager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($this->testUrl);
        return new EntityManager(DriverManager::getConnection($params), $config);
    }
}
