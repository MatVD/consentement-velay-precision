<?php
namespace App\Service;

use App\Entity\ClientLifecycleEvent;
use Doctrine\DBAL\Connection;

final class ClientLifecycleHistoryWriter
{
    public function record(Connection $db, string $type, \DateTimeImmutable $at): void
    {
        if (!in_array($type, [
            ClientLifecycleEvent::ARCHIVED,
            ClientLifecycleEvent::ANONYMIZED,
            ClientLifecycleEvent::DELETED,
        ], true)) {
            throw new \InvalidArgumentException('Événement de cycle de vie inconnu.');
        }
        $month = $at->setTimezone(new \DateTimeZone('UTC'))
            ->modify('first day of this month')->setTime(0, 0)->format('Y-m-d H:i:s');
        // A single generic event per month, regardless of how many clients
        // transitioned. No client ID, actor, count or exact event timestamp.
        $db->executeStatement(
            'INSERT INTO client_lifecycle_event (event_type, created_at) VALUES (:type, :month) ON CONFLICT (event_type, created_at) DO NOTHING',
            ['type' => $type, 'month' => $month]
        );
    }
}
