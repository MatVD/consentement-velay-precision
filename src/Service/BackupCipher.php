<?php
namespace App\Service;

/**
 * Portable, authenticated stream encryption for database + evidence backups.
 *
 * Format: magic (8), Argon2id salt (16), secretstream header (24),
 * then framed secretstream ciphertext with a mandatory final tag.
 * The passphrase is provided outside argv/config and must not be logged.
 */
final class BackupCipher
{
    private const MAGIC = 'VELAYB01';
    private const CHUNK_SIZE = 65536;

    /** @param resource $input @param resource $output */
    public function encrypt($input, $output, string $passphrase): void
    {
        $this->assertPassphrase($passphrase);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = $this->deriveKey($passphrase, $salt);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        sodium_memzero($key);

        $this->writeAll($output, self::MAGIC.$salt.$header);
        while (!feof($input)) {
            $chunk = fread($input, self::CHUNK_SIZE);
            if ($chunk === false) {
                throw new \RuntimeException('Erreur de lecture de la sauvegarde.');
            }
            if ($chunk === '') {
                if (feof($input)) {
                    break;
                }
                throw new \RuntimeException('Lecture de sauvegarde interrompue.');
            }
            $this->writeFrame($output, sodium_crypto_secretstream_xchacha20poly1305_push(
                $state, $chunk, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
            ));
        }
        // An explicit final record detects truncation, including at chunk boundaries.
        $this->writeFrame($output, sodium_crypto_secretstream_xchacha20poly1305_push(
            $state, '', '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
        ));
    }

    /** @param resource $input @param resource $output */
    public function decrypt($input, $output, string $passphrase): void
    {
        $this->assertPassphrase($passphrase);
        if ($this->readExact($input, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new \RuntimeException('Format de sauvegarde non reconnu.');
        }
        $salt = $this->readExact($input, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $header = $this->readExact($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $key = $this->deriveKey($passphrase, $salt);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        sodium_memzero($key);

        while (true) {
            $lenBytes = $this->readExact($input, 4);
            $length = unpack('N', $lenBytes)[1];
            if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                || $length > self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                throw new \RuntimeException('Taille de bloc chiffré invalide.');
            }
            $decoded = sodium_crypto_secretstream_xchacha20poly1305_pull(
                $state, $this->readExact($input, $length)
            );
            if ($decoded === false) {
                throw new \RuntimeException('Sauvegarde altérée ou clé incorrecte.');
            }
            [$plaintext, $tag] = $decoded;
            $this->writeAll($output, $plaintext);
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                if (fread($input, 1) !== '') {
                    throw new \RuntimeException('Données inattendues après la sauvegarde.');
                }
                return;
            }
            if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) {
                throw new \RuntimeException('Balise de sauvegarde non reconnue.');
            }
        }
    }

    private function deriveKey(string $passphrase, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $passphrase,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    private function assertPassphrase(string $passphrase): void
    {
        if (strlen($passphrase) < 24) {
            throw new \InvalidArgumentException('Clé de sauvegarde trop courte (24 octets minimum).');
        }
    }

    /** @param resource $output */
    private function writeFrame($output, string $payload): void
    {
        $this->writeAll($output, pack('N', strlen($payload)).$payload);
    }

    /** @param resource $output */
    private function writeAll($output, string $bytes): void
    {
        while ($bytes !== '') {
            $count = fwrite($output, $bytes);
            if (!is_int($count) || $count <= 0) {
                throw new \RuntimeException('Erreur d’écriture de la sauvegarde.');
            }
            $bytes = substr($bytes, $count);
        }
    }

    /** @param resource $input */
    private function readExact($input, int $size): string
    {
        $result = '';
        while (strlen($result) < $size) {
            $part = fread($input, $size - strlen($result));
            if ($part === false || $part === '') {
                throw new \RuntimeException('Sauvegarde tronquée.');
            }
            $result .= $part;
        }
        return $result;
    }
}
