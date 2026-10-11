<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class AdminResponsiveCssTest extends TestCase
{
    public function testClientIndexMobileSelectorsTargetTheEasyAdminBodyClass(): void
    {
        $css = file_get_contents(dirname(__DIR__).'/public/assets/admin.css');

        self::assertIsString($css);
        self::assertStringContainsString('@media (max-width: 767.98px)', $css);
        self::assertStringContainsString('.ea.ea-index .table.datagrid tbody tr', $css);
        self::assertStringContainsString('.ea.ea-index .table.datagrid tbody td[data-column=\'quickActions\']', $css);
        self::assertStringNotContainsString('.ea.page-index', $css);
    }
}
