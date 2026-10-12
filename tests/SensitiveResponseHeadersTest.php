<?php
namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SensitiveResponseHeadersTest extends WebTestCase
{
    public function testAllResponseTypesHaveGlobalPrivacyHeaders(): void
    {
        $browser = static::createClient();
        foreach (['/', '/login', '/merci', '/admin/client', '/c/'.str_repeat('0', 64)] as $path) {
            $browser->request('GET', $path);
            $headers = $browser->getResponse()->headers;
            self::assertSame('no-referrer', $headers->get('Referrer-Policy'), $path);
            self::assertSame('nosniff', $headers->get('X-Content-Type-Options'), $path);
            self::assertSame('DENY', $headers->get('X-Frame-Options'), $path);
            self::assertStringContainsString('microphone=()', $headers->get('Permissions-Policy'), $path);
            if ($path !== '/') {
                self::assertStringContainsString('no-store', $headers->get('Cache-Control'), $path);
                self::assertStringContainsString('private', $headers->get('Cache-Control'), $path);
            }
        }
    }

    public function testInvalidConsentTokenRemainsExpiredAndUncacheable(): void
    {
        $browser = static::createClient();
        $browser->request('GET', '/c/'.str_repeat('0', 64));
        self::assertResponseStatusCodeSame(410);
        self::assertStringContainsString('no-store', $browser->getResponse()->headers->get('Cache-Control'));
    }
}
