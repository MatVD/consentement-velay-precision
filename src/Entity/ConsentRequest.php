<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'consent_request')]
class ConsentRequest
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(length: 20)]
    private string $channel;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(Client $client, string $tokenHash, string $channel, \DateTimeImmutable $expiresAt)
    {
        $this->client = $client;
        $this->tokenHash = $tokenHash;
        $this->channel = $channel;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int { return $this->id; }
    public function getClient(): Client { return $this->client; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getChannel(): string { return $this->channel; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getUsedAt(): ?\DateTimeImmutable { return $this->usedAt; }
    public function markSent(): void { $this->sentAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
    public function markUsed(\DateTimeImmutable $at): void { $this->usedAt = $at; }
    public function expire(): void { $this->expiresAt = new \DateTimeImmutable('-1 second', new \DateTimeZone('UTC')); }
    public function isOpen(): bool
    {
        return $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
