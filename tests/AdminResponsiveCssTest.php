<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class AdminResponsiveCssTest extends TestCase
{
    private function stylesheet(): string
    {
        $css = file_get_contents(dirname(__DIR__).'/public/assets/admin.css');
        self::assertIsString($css);

        return $css;
    }

    public function testClientCardsOverrideEasyAdminAbsoluteLabelsAndPadding(): void
    {
        $css = $this->stylesheet();

        self::assertStringContainsString('@media (max-width: 767.98px)', $css);
        self::assertStringContainsString('.ea.ea-index-Client .table.datagrid:not(.datagrid-empty)', $css);
        self::assertMatchesRegularExpression(
            '~tbody tr:not\\(\\.empty-row\\) > td\\s*\\{[^}]*position:\\s*static;[^}]*padding-inline-start:\\s*0;~s',
            $css
        );
        self::assertMatchesRegularExpression(
            '~td\\[data-label\\]::before\\s*\\{[^}]*position:\\s*static;[^}]*inline-size:\\s*auto;[^}]*padding:\\s*0;~s',
            $css
        );
        self::assertStringNotContainsString('.ea.page-index', $css);
    }

    public function testClientActionsShowTextOnTabletAndDesktopButIconsOnlyOnMobile(): void
    {
        $css = $this->stylesheet();
        $twig = file_get_contents(dirname(__DIR__).'/templates/admin/client_quick_actions.html.twig');

        self::assertIsString($twig);
        self::assertStringContainsString('.ea .client-action-label { display: inline; }', $css);
        self::assertMatchesRegularExpression(
            '~@media \\(max-width: 767\\.98px\\) \\{.*?\\.ea \\.client-action-label \\{ display: none; \\}~s',
            $css
        );
        self::assertSame(1, substr_count($css, '.ea .client-action-label { display: inline; }'));
        self::assertSame(1, substr_count($css, '.ea .client-action-label { display: none; }'));
        self::assertStringNotContainsString(".btn::after { content: 'Email'; }", $css);
        foreach (['Email', 'Accompagner'] as $label) {
            self::assertStringContainsString('<span class="client-action-label">'.$label.'</span>', $twig);
        }
    }

    public function testResponsiveFollowUpTablesAndNarrowHeaderAreCovered(): void
    {
        $css = $this->stylesheet();
        self::assertMatchesRegularExpression(
            '~\\.responsive-data-table tbody td\\s*\\{[^}]*display:\\s*block;[^}]*overflow-wrap:\\s*anywhere;~s',
            $css
        );
        self::assertStringContainsString('.responsive-header #responsive-header-logo', $css);
        self::assertStringContainsString('text-overflow: ellipsis', $css);
        self::assertStringContainsString('@media (max-width: 400px)', $css);
        self::assertStringContainsString('.ea .csv-example', $css);
    }
}
