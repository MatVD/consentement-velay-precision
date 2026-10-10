<?php
namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentRequest;
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
        if (in_array($client->getStatus(), [Client::YES, Client::NO], true)) {
            throw new \DomainException('Une décision définitive est déjà enregistrée.');
        }
        if (!in_array($channel, ['email', 'onsite'], true)) {
            throw new \InvalidArgumentException('Canal inconnu.');
        }
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable($channel === 'email' ? '+14 days' : '+1 day', new \DateTimeZone('UTC'));
        $request = new ConsentRequest($client, hash('sha256', $token), $channel, $expiresAt);
        $this->em->persist($request);
        $this->em->persist(new AuditEvent($client, $request, 'link_created', 'admin', ['channel' => $channel]));
        $this->em->flush();

        return [$request, $this->publicUrl($token)];
    }

    public function sendEmail(Client $client): void
    {
        if (!$client->getEmail()) {
            throw new \DomainException('Renseigner l’adresse email du client avant l’envoi.');
        }
        if (str_starts_with((string) getenv('MAILER_DSN'), 'null:')) {
            throw new \DomainException('Configurer un transport SMTP avant l’envoi.');
        }
        [$invitation, $link] = $this->create($client, 'email');
        $email = (new Email())
            ->from($this->mailFrom)
            ->to($client->getEmail())
            ->subject('Velay Précision - votre choix concernant la signature électronique')
            ->text("Bonjour,\n\nVelay Précision vous invite à indiquer si vous acceptez le recours à la signature électronique pour nos futurs documents.\n\nFormulaire : {$link}\n\nCe lien est individuel et expire dans 14 jours. Aucune inscription n’est nécessaire.\n\nVelay Précision");
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $invitation->expire();
            $this->em->persist(new AuditEvent($client, $invitation, 'email_failed', 'system', ['message' => 'Transport email en échec']));
            $this->em->flush();
            throw $e;
        }
        $invitation->markSent();
        $client->changeStatus(Client::SENT, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->em->persist(new AuditEvent($client, $invitation, 'email_sent', 'admin', ['to' => $client->getEmail()]));
        $this->em->flush();
    }

    private function publicUrl(string $token): string
    {
        return rtrim($this->publicBaseUrl, '/').'/c/'.$token;
    }
}
