<?php
namespace App\Tests;

use App\Service\BackupCipher;
use PHPUnit\Framework\TestCase;

final class BackupCipherTest extends TestCase
{
    private const KEY = 'fixture-secret-not-for-production-12345';

    public function testRoundTripAuthenticatesLargeBinaryStream(): void
    {
        $data = random_bytes(160_000)."\0".random_bytes(131_072);
        $cipher = new BackupCipher();
        $in = $this->stream($data);
        $encrypted = $this->stream();
        $cipher->encrypt($in, $encrypted, self::KEY);
        rewind($encrypted);
        $payload = stream_get_contents($encrypted);
        self::assertStringStartsWith('VELAYB01', $payload);
        self::assertStringNotContainsString(substr($data, 0, 32), $payload);

        $decrypted = $this->stream();
        rewind($encrypted);
        $cipher->decrypt($encrypted, $decrypted, self::KEY);
        rewind($decrypted);
        self::assertSame($data, stream_get_contents($decrypted));
        fclose($in);
        fclose($encrypted);
        fclose($decrypted);
    }

    public function testRejectsAlteredCiphertext(): void
    {
        $contents = $this->encrypted('Confidentiel');
        $contents[strlen($contents) - 12] = chr(ord($contents[strlen($contents) - 12]) ^ 0x10);
        $this->expectException(\RuntimeException::class);
        (new BackupCipher())->decrypt($this->stream($contents), $this->stream(), self::KEY);
    }

    public function testRejectsTruncatedFinalRecord(): void
    {
        $contents = $this->encrypted('Contenu');
        $this->expectException(\RuntimeException::class);
        (new BackupCipher())->decrypt($this->stream(substr($contents, 0, -10)), $this->stream(), self::KEY);
    }

    public function testRejectsIncorrectPassphrase(): void
    {
        $this->expectException(\RuntimeException::class);
        (new BackupCipher())->decrypt(
            $this->stream($this->encrypted('Sauvegarde')),
            $this->stream(),
            'an-incorrect-passphrase-123456789'
        );
    }

    public function testRejectsShortSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BackupCipher())->encrypt($this->stream('data'), $this->stream(), 'short');
    }

    private function encrypted(string $bytes): string
    {
        $out = $this->stream();
        (new BackupCipher())->encrypt($this->stream($bytes), $out, self::KEY);
        rewind($out);
        $result = stream_get_contents($out);
        fclose($out);
        return $result;
    }

    /** @return resource */
    private function stream(string $contents = '')
    {
        $stream = fopen('php://temp', 'w+b');
        if ($contents !== '') {
            fwrite($stream, $contents);
            rewind($stream);
        }
        return $stream;
    }
}
