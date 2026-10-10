<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_event')]
class AuditEvent
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: ConsentRequest::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ConsentRequest $request;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(length: 20)]
    private string $actorType;

    #[ORM\Column(type: 'json')]
    private array $details;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Client $client, ?ConsentRequest $request, string $type, string $actorType, array $details = [])
    {
        $this->client = $client;
        $this->request = $request;
        $this->type = $type;
        $this->actorType = $actorType;
        $this->details = $details;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getActorType(): string { return $this->actorType; }
    public function getDetails(): array { return $this->details; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
