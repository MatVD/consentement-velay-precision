<?php
namespace App\Service;

use App\Dto\ConsentSubmission;
use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentDecision;
use App\Entity\ConsentRequest;
use App\Entity\EvidenceFile;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ConsentRecorder
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EvidenceArchive $archive) {}

    public function record(string $token, ConsentSubmission $submission, Request $httpRequest): ConsentDecision
    {
        if (!in_array($submission->choice, ['yes', 'no'], true)) {
            throw new \InvalidArgumentException('Décision invalide.');
        }
        $createdEvidence = null;
        try {
            return $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($token, $submission, $httpRequest, &$createdEvidence): ConsentDecision {
                $invitation = $em->getRepository(ConsentRequest::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);
                if (!$invitation) { throw new \DomainException('Lien introuvable.'); }

                $em->lock($invitation, LockMode::PESSIMISTIC_WRITE);
                $client = $invitation->getClient();
                $em->lock($client, LockMode::PESSIMISTIC_WRITE);
                if (!$invitation->isOpen() || in_array($client->getStatus(), [Client::YES, Client::NO], true)) {
                    throw new \DomainException('Ce formulaire n’est plus disponible.');
                }
                $at = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $decision = new ConsentDecision(
                    $client, $invitation, trim($submission->firstName), trim($submission->lastName),
                    trim($submission->signatoryRole), $submission->choice,
                    ConsentPolicy::VERSION, ConsentPolicy::TEXT, $at,
                    $httpRequest->getClientIp(), mb_substr($httpRequest->headers->get('User-Agent', ''), 0, 500)
                );
                $em->persist($decision);
                $em->flush();
                $createdEvidence = $this->archive->createReceipt($decision);
                $em->persist($createdEvidence);
                $invitation->markUsed($at);
                $client->changeStatus($submission->choice === 'yes' ? Client::YES : Client::NO, $at);
                $em->persist(new AuditEvent($client, $invitation, 'decision_recorded', 'client', [
                    'choice' => $submission->choice,
                    'text_version' => ConsentPolicy::VERSION,
                    'payload_hash' => $decision->getPayloadHash(),
                ]));
                return $decision;
            });
        } catch (\Throwable $exception) {
            if ($createdEvidence instanceof EvidenceFile) { $this->archive->remove($createdEvidence); }
            throw $exception;
        }
    }
}
