<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'consent_decision')]
#[ORM\UniqueConstraint(name: 'UNIQ_DECISION_CLIENT', columns: ['client_id'])]
class ConsentDecision
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\OneToOne(targetEntity: ConsentRequest::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ConsentRequest $request;

    #[ORM\Column(length: 120)]
    private string $firstName;

    #[ORM\Column(length: 120)]
    private string $lastName;

    #[ORM\Column(length: 120)]
    private string $signatoryRole;

    #[ORM\Column(length: 3)]
    private string $choice;

    #[ORM\Column(length: 40)]
    private string $textVersion;

    #[ORM\Column(type: 'text')]
    private string $textContent;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $decidedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(length: 64)]
    private string $payloadHash;

    public function __construct(Client $client, ConsentRequest $request, string $firstName, string $lastName, string $signatoryRole, string $choice, string $textVersion, string $textContent, \DateTimeImmutable $at, ?string $ipAddress, ?string $userAgent)
    {
        $this->client = $client;
        $this->request = $request;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->signatoryRole = $signatoryRole;
        $this->choice = $choice;
        $this->textVersion = $textVersion;
        $this->textContent = $textContent;
        $this->decidedAt = $at;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->payloadHash = hash('sha256', json_encode([
            'client_id' => $client->getId(), 'request_id' => $request->getId(),
            'first_name' => $firstName, 'last_name' => $lastName, 'role' => $signatoryRole,
            'choice' => $choice, 'text_version' => $textVersion, 'text' => $textContent,
            'date_utc' => $at->format('Y-m-d\TH:i:s.u\Z'),
            'ip' => $ipAddress, 'user_agent' => $userAgent,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function getId(): ?int { return $this->id; }
    public function getClient(): Client { return $this->client; }
    public function getRequest(): ConsentRequest { return $this->request; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getSignatoryRole(): string { return $this->signatoryRole; }
    public function getChoice(): string { return $this->choice; }
    public function getTextVersion(): string { return $this->textVersion; }
    public function getTextContent(): string { return $this->textContent; }
    public function getDecidedAt(): \DateTimeImmutable { return $this->decidedAt; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getPayloadHash(): string { return $this->payloadHash; }
}
