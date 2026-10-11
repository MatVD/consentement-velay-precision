<?php
namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ResponsivePagesTest extends WebTestCase
{
    /**
     * @dataProvider publicPages
     */
    public function testPublicPagesUseFluidMobileViewport(string $uri): void
    {
        $browser = static::createClient();
        $browser->request('GET', $uri);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="viewport"][content="width=device-width, initial-scale=1"]');
        self::assertSelectorExists('link[rel="stylesheet"][href$="/assets/app.css"]');
    }

    public static function publicPages(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'connexion' => ['/login'];
        yield 'confirmation' => ['/merci'];
    }
}
