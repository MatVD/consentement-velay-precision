<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Recoverable queue for deleting physical files after the SQL transaction. */
#[ORM\Entity]
#[ORM\Table(name: 'evidence_purge_queue')]
#[ORM\UniqueConstraint(name: 'UNIQ_EVIDENCE_PURGE_PATH', columns: ['path'])]
final class EvidencePurgeQueue
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $path)
    {
        if (basename($path) !== $path || !preg_match('/^decision-[a-f0-9]{32}\.pdf$/D', $path)) {
            throw new \InvalidArgumentException('Nom de preuve non autorisé.');
        }
        $this->path = $path;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int { return $this->id; }
    public function getPath(): string { return $this->path; }
}
