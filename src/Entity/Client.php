<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'client')]
#[ORM\Index(name: 'IDX_CLIENT_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_CLIENT_CODIAL', columns: ['codial_id'])]
#[ORM\HasLifecycleCallbacks]
class Client
{
    public const TODO = 'todo';
    public const SENT = 'sent';
    public const YES = 'yes';
    public const NO = 'no';

    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $codialId = null;

    #[ORM\Column(length: 20)]
    private string $status = self::TODO;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $statusAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email === null || trim($email) === '' ? null : mb_strtolower(trim($email)); return $this; }
    public function getCodialId(): ?string { return $this->codialId; }
    public function setCodialId(?string $id): self { $this->codialId = $id === null || trim($id) === '' ? null : trim($id); return $this; }
    public function getStatus(): string { return $this->status; }
    public function getStatusLabel(): string
    {
        return match ($this->status) { self::SENT => 'Mail envoyé', self::YES => 'Oui', self::NO => 'Non', default => 'À faire' };
    }
    public function getStatusAt(): ?\DateTimeImmutable { return $this->statusAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** Seul le service métier doit déclencher un changement de statut. */
    public function changeStatus(string $newStatus, \DateTimeImmutable $at): void
    {
        if (!in_array($newStatus, [self::TODO, self::SENT, self::YES, self::NO], true)) {
            throw new \InvalidArgumentException('Statut non reconnu.');
        }
        if (in_array($this->status, [self::YES, self::NO], true) && $this->status !== $newStatus) {
            throw new \DomainException('La décision est définitive dans cette V1.');
        }
        $this->status = $newStatus;
        $this->statusAt = $at;
        $this->updatedAt = $at;
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
    public function __toString(): string { return $this->name; }
}
