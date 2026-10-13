<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Month-level cohort count only. No client ID, choice, PDF, event ID or
 * individual date is retained. Runs with fewer than five clients are
 * intentionally NOT represented in this table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'retention_aggregate')]
final class RetentionAggregate
{
    #[ORM\Id]
    #[ORM\Column(length: 7)]
    private string $period;

    #[ORM\Column(nullable: true)]
    private ?int $clientTotal;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $expiresOn;

    public function __construct(string $period, ?int $clientTotal, \DateTimeImmutable $expiresOn)
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period) || ($clientTotal !== null && $clientTotal < 5)) {
            throw new \InvalidArgumentException('Cohorte anonyme invalide.');
        }
        $this->period = $period;
        $this->clientTotal = $clientTotal;
        $this->expiresOn = $expiresOn;
    }

    public function getPeriod(): string { return $this->period; }
    public function getClientTotal(): ?int { return $this->clientTotal; }
    public function getExpiresOn(): \DateTimeImmutable { return $this->expiresOn; }
}
