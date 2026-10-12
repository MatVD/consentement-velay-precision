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

    public function recordAssisted(Client $client, ConsentSubmission $submission, Request $httpRequest, string $operator): ConsentDecision
    {
        if (!in_array($submission->choice, ['yes', 'no'], true)) {
            throw new \InvalidArgumentException('Décision invalide.');
        }

        $createdEvidence = null;
        try {
            return $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($client, $submission, $httpRequest, $operator, &$createdEvidence): ConsentDecision {
                $em->refresh($client, LockMode::PESSIMISTIC_WRITE);
                if (!$client->isActive()) {
                    throw new \DomainException('Client archivé : aucune décision ne peut être enregistrée.');
                }
                if (in_array($client->getStatus(), [Client::YES, Client::NO], true)) {
                    throw new \DomainException('Une décision définitive est déjà enregistrée.');
                }
                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $invitation = new ConsentRequest($client, hash('sha256', random_bytes(32)), 'onsite', $now->modify('+1 day'));
                $em->persist($invitation);
                $em->flush();
                $decision = new ConsentDecision(
                    $client, $invitation, trim($submission->firstName), trim($submission->lastName),
                    trim($submission->signatoryRole), $submission->choice,
                    ConsentPolicy::VERSION, ConsentPolicy::TEXT, $now,
                    $httpRequest->getClientIp(), mb_substr($httpRequest->headers->get('User-Agent', ''), 0, 500)
                );
                $em->persist($decision);
                $em->flush();
                $createdEvidence = $this->archive->createReceipt($decision);
                $em->persist($createdEvidence);
                $invitation->markUsed($now);
                $client->changeStatus($submission->choice === 'yes' ? Client::YES : Client::NO, $now);
                $em->persist(new AuditEvent($client, $invitation, 'decision_recorded', 'admin', [
                    'channel' => 'assisted',
                    'operator' => $operator,
                    'choice' => $submission->choice,
                    'text_version' => ConsentPolicy::VERSION,
                    'payload_hash' => $decision->getPayloadHash(),
                ]));
                return $decision;
            });
        } catch (\Throwable $exception) {
            if ($createdEvidence instanceof EvidenceFile) {
                $this->archive->remove($createdEvidence);
            }
            throw $exception;
        }
    }

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

                // Always lock the client first (as InvitationService does), then
                // refresh the invitation. This also prevents stale status reads.
                $client = $invitation->getClient();
                $em->refresh($client, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($invitation, LockMode::PESSIMISTIC_WRITE);
                if (!$client->isActive() || !$invitation->isOpen() || in_array($client->getStatus(), [Client::YES, Client::NO], true)) {
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
