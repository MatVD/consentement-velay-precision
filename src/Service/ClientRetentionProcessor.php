<?php
namespace App\Service;

use App\Entity\ClientLifecycleEvent;
use Doctrine\DBAL\Connection;

final class ClientRetentionProcessor
{
    public const MIN_PUBLIC_COHORT = 5;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClientLifecycleHistoryWriter $history,
        private readonly EvidencePurgeExecutor $evidencePurger,
        private readonly ClientRetentionSchedule $schedule,
    ) {}

    /** @return array{due_clients:int, expired_aggregates:int, pending_pdfs:int} */
    public function preview(\DateTimeImmutable $now): array
    {
        $cutoff = $now->setTimezone(new \DateTimeZone('UTC'))->modify('-1 year')->format('Y-m-d H:i:s');
        return [
            'due_clients' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM client WHERE lifecycle_stage = :stage AND contract_ended_at <= :cutoff',
                ['stage' => 'archived', 'cutoff' => $cutoff]
            ),
            'expired_aggregates' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM retention_aggregate WHERE expires_on <= :day',
                ['day' => $now->format('Y-m-d')]
            ),
            'pending_pdfs' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM evidence_purge_queue'),
        ];
    }

    /**
     * Destructive execution. Must only be called from an explicitly gated
     * operator command, or from isolated tests with entirely synthetic data.
     *
     * @return array{anonymized:int, aggregate_counted:int, statistics_expired:int, pdfs_removed:int, pdfs_pending:int}
     */
    public function process(\DateTimeImmutable $now, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Limite autorisée : 1 à 500.');
        }
        $utc = $now->setTimezone(new \DateTimeZone('UTC'));
        $cutoff = $utc->modify('-1 year')->format('Y-m-d H:i:s');
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM client WHERE lifecycle_stage = :stage AND contract_ended_at <= :cutoff ORDER BY id LIMIT '.$limit,
            ['stage' => 'archived', 'cutoff' => $cutoff]
        );
        // SQL removals, aggregate counters and global history commit together.
        // A failure cannot leave "deleted client but missing cohort".
        [$anonymized, $counted, $expired] = $this->connection->transactional(
            function (Connection $db) use ($ids, $utc): array {
                $anonymized = 0;
                $counted = 0;
                $tooOld = 0;
                $cohorts = [];
                foreach ($ids as $id) {
                    $period = $this->purgeOne((int) $id, $utc);
                    if ($period === false) {
                        continue;
                    }
                    ++$anonymized;
                    if ($period === '') {
                        // This client's three-year deadline already passed.
                        // Do not create a new anonymous archive.
                        ++$tooOld;
                    } else {
                        $cohorts[$period] = ($cohorts[$period] ?? 0) + 1;
                    }
                }
                foreach ($cohorts as $period => $cohortSize) {
                    // No count of 1-4 is ever stored. Even with multiple
                    // small runs, no individually attributable counter exists.
                    $published = $cohortSize >= self::MIN_PUBLIC_COHORT ? $cohortSize : null;
                    $counted += $published ?? 0;
                    $month = new \DateTimeImmutable($period.'-01', new \DateTimeZone('UTC'));
                    $expires = $month->modify('first day of next month')
                        ->modify('+3 years')->format('Y-m-d');
                    $db->executeStatement(
                        'INSERT INTO retention_aggregate (period, client_total, expires_on)
                         VALUES (:period, :count, :expires)
                         ON CONFLICT (period) DO UPDATE SET client_total =
                         CASE WHEN EXCLUDED.client_total IS NULL THEN retention_aggregate.client_total
                         ELSE COALESCE(retention_aggregate.client_total, 0) + EXCLUDED.client_total END',
                        ['period' => $period, 'count' => $published, 'expires' => $expires]
                    );
                }
                if ($anonymized > 0) {
                    $this->history->record($db, ClientLifecycleEvent::ANONYMIZED, $utc);
                }
                $expired = $db->executeStatement(
                    'DELETE FROM retention_aggregate WHERE expires_on <= :day',
                    ['day' => $utc->format('Y-m-d')]
                );
                if ($expired > 0 || $tooOld > 0) {
                    $this->history->record($db, ClientLifecycleEvent::DELETED, $utc);
                }
                return [$anonymized, $counted, $expired];
            }
        );
        $files = $this->evidencePurger->drain();
        return [
            'anonymized' => $anonymized,
            'aggregate_counted' => $counted,
            'statistics_expired' => $expired,
            'pdfs_removed' => $files['removed'],
            'pdfs_pending' => $files['pending'],
        ];
    }

    /** @return string|false Contract-end month (YYYY-MM), '' if already >3 years old, false if not due. */
    private function purgeOne(int $id, \DateTimeImmutable $now): string|false
    {
        return $this->connection->transactional(function (Connection $db) use ($id, $now): string|false {
            $row = $db->fetchAssociative(
                'SELECT id, lifecycle_stage, contract_ended_at FROM client WHERE id = :id FOR UPDATE',
                ['id' => $id]
            );
            if (!$row || $row['lifecycle_stage'] !== 'archived' || $row['contract_ended_at'] === null) {
                return false;
            }
            $ended = new \DateTimeImmutable((string) $row['contract_ended_at'], new \DateTimeZone('UTC'));
            if ($this->schedule->deadlines($ended)['anonymize_after'] > $now) {
                return false;
            }

            $paths = $db->fetchFirstColumn(
                'SELECT e.path FROM evidence_file e
                 INNER JOIN consent_decision d ON d.id = e.decision_id WHERE d.client_id = :id',
                ['id' => $id]
            );
            foreach ($paths as $path) {
                // Outbox rows hold only opaque PDF filenames and cannot point
                // to arbitrary files. The file is NOT unlinked inside SQL txn.
                if (!preg_match('/^decision-[a-f0-9]{32}\.pdf$/D', (string) $path)) {
                    throw new \RuntimeException('Nom de preuve non conforme : purge interrompue.');
                }
                $db->executeStatement(
                    'INSERT INTO evidence_purge_queue (path, created_at)
                     VALUES (:path, :now) ON CONFLICT (path) DO NOTHING',
                    ['path' => $path, 'now' => $now->format('Y-m-d H:i:s')]
                );
            }

            // Explicit FK order: PDF metadata, individual audit, decision,
            // invitations, then the client. No individual record survives.
            $db->executeStatement(
                'DELETE FROM evidence_file WHERE decision_id IN (SELECT id FROM consent_decision WHERE client_id = :id)',
                ['id' => $id]
            );
            $db->executeStatement('DELETE FROM audit_event WHERE client_id = :id', ['id' => $id]);
            $db->executeStatement('DELETE FROM consent_decision WHERE client_id = :id', ['id' => $id]);
            $db->executeStatement('DELETE FROM consent_request WHERE client_id = :id', ['id' => $id]);
            $db->executeStatement('DELETE FROM client WHERE id = :id', ['id' => $id]);
            return $this->schedule->deadlines($ended)['delete_after'] <= $now
                ? '' : $ended->format('Y-m');
        });
    }
}
