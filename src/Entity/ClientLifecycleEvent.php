<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Global, deliberately non-identifying lifecycle timeline.
 * NO client, Codial, request, user, PDF or IP reference belongs here.
 * The per-client, identifying audit is removed at actual anonymisation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'client_lifecycle_event')]
#[ORM\Index(name: 'IDX_CLIENT_LIFECYCLE_EVENT_TIME', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'UNIQ_LIFECYCLE_MONTH_TYPE', columns: ['event_type', 'created_at'])]
class ClientLifecycleEvent
{
    public const ARCHIVED = 'archived';
    public const ANONYMIZED = 'anonymized';
    public const DELETED = 'deleted';

    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $eventType;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $eventType, ?\DateTimeImmutable $at = null)
    {
        if (!in_array($eventType, [self::ARCHIVED, self::ANONYMIZED, self::DELETED], true)) {
            throw new \InvalidArgumentException('Événement d’archivage inconnu.');
        }
        $this->eventType = $eventType;
        // Coarse month bucket; no timestamp specific to an individual client.
        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now->setTimezone(new \DateTimeZone('UTC'))
            ->modify('first day of this month')->setTime(0, 0);
    }

    public function getId(): ?int { return $this->id; }
    public function getEventType(): string { return $this->eventType; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLabel(): string
    {
        return match ($this->eventType) {
            self::ARCHIVED => 'Client archivé',
            self::ANONYMIZED => 'Client anonymisé',
            self::DELETED => 'Client supprimé',
        };
    }
}
