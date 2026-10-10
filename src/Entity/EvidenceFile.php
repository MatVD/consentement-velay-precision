<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'evidence_file')]
class EvidenceFile
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConsentDecision::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ConsentDecision $decision;

    #[ORM\Column(length: 40)]
    private string $kind;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column(length: 64)]
    private string $sha256;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(ConsentDecision $decision, string $kind, string $path, string $mimeType, string $sha256)
    {
        $this->decision = $decision;
        $this->kind = $kind;
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->sha256 = $sha256;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function getId(): ?int { return $this->id; }
    public function getDecision(): ConsentDecision { return $this->decision; }
    public function getPath(): string { return $this->path; }
    public function getKind(): string { return $this->kind; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSha256(): string { return $this->sha256; }
}
