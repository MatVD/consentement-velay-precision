<?php
namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentRequest;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class InvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly string $publicBaseUrl,
        private readonly string $mailFrom,
    ) {}

    /** @return array{0: ConsentRequest, 1: string} */
    public function create(Client $client, string $channel): array
    {
        if (!in_array($channel, ['email', 'onsite'], true)) {
            throw new \InvalidArgumentException('Canal inconnu.');
        }

        // The client lock is shared with ConsentRecorder: no invitation can be
        // created from an outdated status after a final decision is committed.
        return $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($client, $channel): array {
            $em->refresh($client, LockMode::PESSIMISTIC_WRITE);
            if (!$client->isActive()) {
                throw new \DomainException('La relation contractuelle est terminée : client archivé.');
            }
            if (in_array($client->getStatus(), [Client::YES, Client::NO], true)) {
                throw new \DomainException('Une décision définitive est déjà enregistrée.');
            }
            if ($channel === 'email' && !$client->getEmail()) {
                throw new \DomainException('Renseigner l’adresse email du client avant l’envoi.');
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = new \DateTimeImmutable($channel === 'email' ? '+14 days' : '+1 day', new \DateTimeZone('UTC'));
            $invitation = new ConsentRequest($client, hash('sha256', $token), $channel, $expiresAt);
            $em->persist($invitation);
            $em->persist(new AuditEvent($client, $invitation, 'link_created', 'admin', ['channel' => $channel]));

            return [$invitation, $this->publicUrl($token)];
        });
    }

    public function sendEmail(Client $client): void
    {
        if (str_starts_with((string) getenv('MAILER_DSN'), 'null:')) {
            throw new \DomainException('Configurer un transport SMTP avant l’envoi.');
        }

        [$invitation, $link] = $this->create($client, 'email');
        $recipient = $client->getEmail();
        $email = (new Email())
            ->from($this->mailFrom)
            ->to($recipient)
            ->subject('Velay Précision - votre choix concernant la signature électronique')
            ->text("Bonjour,\n\nVelay Précision vous invite à indiquer si vous acceptez le recours à la signature électronique pour nos futurs documents.\n\nFormulaire : {$link}\n\nCe lien est individuel et expire dans 14 jours. Aucune inscription n’est nécessaire.\n\nVelay Précision");
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use ($client, $invitation): void {
                $em->refresh($client, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($invitation, LockMode::PESSIMISTIC_WRITE);
                if ($invitation->getUsedAt() === null) {
                    $invitation->expire();
                }
                $em->persist(new AuditEvent($client, $invitation, 'email_failed', 'system', ['message' => 'Transport email en échec']));
            });
            throw $e;
        }

        // SMTP is external to the DB transaction. Re-read the client under a
        // lock: a decision may have been recorded while the email was sent.
        $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use ($client, $invitation, $recipient): void {
            $em->refresh($client, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($invitation, LockMode::PESSIMISTIC_WRITE);
            $invitation->markSent();
            $statusUpdated = $client->isActive() && !in_array($client->getStatus(), [Client::YES, Client::NO], true);
            if ($statusUpdated) {
                $client->changeStatus(Client::SENT, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            }
            $em->persist(new AuditEvent($client, $invitation, 'email_sent', 'admin', [
                'to' => $recipient,
                'status_updated' => $statusUpdated,
            ]));
        });
    }

    private function publicUrl(string $token): string
    {
        return rtrim($this->publicBaseUrl, '/').'/c/'.$token;
    }
}
