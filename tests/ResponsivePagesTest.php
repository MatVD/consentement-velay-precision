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

    public function testUnknownConsentLinkStillHasMobileFriendlyErrorPage(): void
    {
        $browser = static::createClient();
        $browser->request('GET', '/c/'.str_repeat('0', 64));

        self::assertResponseStatusCodeSame(410);
        self::assertSelectorExists('meta[name="viewport"][content="width=device-width, initial-scale=1"]');
        self::assertSelectorExists('link[rel="stylesheet"][href$="/assets/app.css"]');
    }

    public function testConsentDecisionCardsUseTwoDesktopColumnsAndOneMobileColumn(): void
    {
        $css = file_get_contents(dirname(__DIR__).'/public/assets/app.css');
        self::assertIsString($css);
        self::assertMatchesRegularExpression(
            '~\\.choice-cards\\s*\\{\\s*display:\\s*grid;\\s*grid-template-columns:\\s*minmax\\(0, 1fr\\);~',
            $css
        );
        self::assertStringContainsString('@media (min-width: 640px)', $css);
        self::assertStringContainsString('.choice-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $css);
        self::assertStringContainsString('.choice-area .choice-card { min-height: 180px; }', $css);
        self::assertStringContainsString(".choice-card:has(input[type='radio']:checked)", $css);
        self::assertStringContainsString(".choice-card:has(input[type='radio']:focus-visible)", $css);
    }

    public function testAffirmativeConsentCardHasPastelSuccessStyling(): void
    {
        $css = file_get_contents(dirname(__DIR__).'/public/assets/app.css');
        self::assertIsString($css);
        self::assertStringContainsString(
            ".choice-area .choice-card:has(input[type='radio'][value='yes']) {",
            $css
        );
        self::assertStringContainsString('background: #eff9f1;', $css);
        self::assertStringContainsString('color: #247044;', $css);
        self::assertStringContainsString(
            ".choice-area .choice-card:has(input[type='radio'][value='yes']:checked) {",
            $css
        );
        self::assertStringContainsString('background: #e0f4e6;', $css);
    }

    public static function publicPages(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'connexion' => ['/login'];
        yield 'confirmation' => ['/merci'];
    }
}
