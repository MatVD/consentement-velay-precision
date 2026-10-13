<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * SQL deletes are committed before physical files are unlinked. A failed
 * unlink leaves an outbox item for retry, avoiding lost evidence on rollback.
 */
final class EvidencePurgeExecutor
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $evidenceDirectory,
    ) {}

    /** @return array{removed: int, pending: int} */
    public function drain(int $limit = 200): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Limite de traitement invalide.');
        }
        $items = $this->connection->fetchAllAssociative(
            'SELECT id, path FROM evidence_purge_queue ORDER BY id ASC LIMIT '.$limit
        );
        $removed = 0;
        foreach ($items as $item) {
            $basename = (string) $item['path'];
            if (!preg_match('/^decision-[a-f0-9]{32}\.pdf$/D', $basename) || basename($basename) !== $basename) {
                // Never open/delete arbitrary paths from a malformed queue.
                continue;
            }
            $fullPath = rtrim($this->evidenceDirectory, '/').'/'.$basename;
            if (is_link($fullPath)) {
                continue;
            }
            if (is_file($fullPath) && !@unlink($fullPath)) {
                continue; // Retry without deleting the outbox record.
            }
            if (file_exists($fullPath)) {
                continue;
            }
            $this->connection->executeStatement(
                'DELETE FROM evidence_purge_queue WHERE id = :id', ['id' => $item['id']]
            );
            ++$removed;
        }
        return [
            'removed' => $removed,
            'pending' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM evidence_purge_queue'),
        ];
    }
}
