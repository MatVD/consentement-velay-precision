<?php
namespace App\Service;

/**
 * The proposed business timeline, not a deletion authorization.
 * Destructive transitions are deliberately NOT implemented or scheduled.
 */
final class ClientRetentionSchedule
{
    public const NORMAL_ARCHIVE_YEARS = 1;
    public const ANONYMIZED_ARCHIVE_YEARS = 2;

    /** @return array{anonymize_after: \DateTimeImmutable, delete_after: \DateTimeImmutable} */
    public function deadlines(\DateTimeImmutable $contractEndedAt): array
    {
        $anonymize = $contractEndedAt->modify('+1 year');
        return [
            'anonymize_after' => $anonymize,
            'delete_after' => $anonymize->modify('+2 years'),
        ];
    }
}
