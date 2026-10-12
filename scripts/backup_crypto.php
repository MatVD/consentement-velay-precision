<?php
declare(strict_types=1);

// Called only inside the application container by scripts/backup.py.
// The encryption passphrase travels via inherited environment, never argv.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\BackupCipher;

try {
    $mode = $argv[1] ?? '';
    $key = getenv('VELAY_BACKUP_SECRET');
    if (!in_array($mode, ['encrypt', 'decrypt'], true) || !is_string($key) || strlen($key) < 24) {
        throw new RuntimeException('Mode de sauvegarde ou secret manquant.');
    }
    $cipher = new BackupCipher();
    if ($mode === 'encrypt') {
        $cipher->encrypt(STDIN, STDOUT, $key);
    } else {
        $cipher->decrypt(STDIN, STDOUT, $key);
    }
} catch (Throwable $e) {
    // Never expose secrets, decrypted bytes or file paths in the error.
    fwrite(STDERR, "Echec du chiffrement / déchiffrement.\n");
    exit(1);
}
