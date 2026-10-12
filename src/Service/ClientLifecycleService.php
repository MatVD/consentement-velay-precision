<?php
namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ClientLifecycleEvent;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class ClientLifecycleService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Explicit, reversible closure step. The original decision and PDF are
     * preserved during the first 12 months. No automatic erase is enabled.
     */
    public function archive(Client $client, \DateTimeImmutable $endedAt): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($endedAt > $now) {
            throw new \DomainException('La date de fin du contrat ne peut pas être future.');
        }

        $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use ($client, $endedAt, $now): void {
            $em->refresh($client, LockMode::PESSIMISTIC_WRITE);
            $client->archiveContract($endedAt, $now);
            $em->persist(new AuditEvent($client, null, 'client_archived', 'admin', [
                'contract_ended_at' => $endedAt->format('Y-m-d'),
            ]));
            // Global history intentionally has no client ID/name, email or actor.
            $em->persist(new ClientLifecycleEvent(ClientLifecycleEvent::ARCHIVED, $now));
        });
    }
}
