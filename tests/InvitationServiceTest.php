<?php
namespace App\Tests;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentRequest;
use App\Service\InvitationService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

final class InvitationServiceTest extends TestCase
{
    public function testNoNewLinkWhenConcurrentDecisionIsVisibleAfterLock(): void
    {
        $client = (new Client())->setName('Magasin');
        $em = $this->transactionalEntityManager();
        $em->expects(self::once())->method('refresh')
            ->with($client, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(static function () use ($client): void {
                $client->changeStatus(Client::YES, new \DateTimeImmutable());
            });
        $em->expects(self::never())->method('persist');

        $service = new InvitationService($em, $this->createMock(MailerInterface::class), 'http://localhost', 'contact@example.fr');
        $this->expectException(\DomainException::class);
        $service->create($client, 'onsite');
    }

    public function testSuccessfulEmailDoesNotOverwriteDecisionMadeDuringDelivery(): void
    {
        $client = (new Client())->setName('Commerce')->setEmail('contact@example.fr');
        $persisted = [];
        $em = $this->transactionalEntityManager();
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->expects(self::exactly(3))->method('refresh');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')
            ->willReturnCallback(static function () use ($client): void {
                $client->changeStatus(Client::NO, new \DateTimeImmutable());
            });

        (new InvitationService($em, $mailer, 'http://localhost', 'contact@example.fr'))->sendEmail($client);

        self::assertSame(Client::NO, $client->getStatus());
        $invitation = $this->firstOfType($persisted, ConsentRequest::class);
        self::assertNotNull($invitation->getSentAt());
        $events = array_values(array_filter($persisted, static fn (object $e): bool => $e instanceof AuditEvent && $e->getType() === 'email_sent'));
        self::assertCount(1, $events);
        self::assertFalse($events[0]->getDetails()['status_updated']);
    }

    public function testSuccessfulEmailMarksPendingClientAsSent(): void
    {
        $client = (new Client())->setName('Commerce')->setEmail('contact@example.fr');
        $persisted = [];
        $em = $this->transactionalEntityManager();
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->expects(self::exactly(3))->method('refresh');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        (new InvitationService($em, $mailer, 'http://localhost', 'contact@example.fr'))->sendEmail($client);

        self::assertSame(Client::SENT, $client->getStatus());
        $event = array_values(array_filter($persisted, static fn (object $e): bool => $e instanceof AuditEvent && $e->getType() === 'email_sent'))[0];
        self::assertTrue($event->getDetails()['status_updated']);
    }

    public function testFailedTransportExpiresUnusedLinkWithoutChangingStatus(): void
    {
        $client = (new Client())->setName('Commerce')->setEmail('contact@example.fr');
        $persisted = [];
        $em = $this->transactionalEntityManager();
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('SMTP indisponible'));

        try {
            (new InvitationService($em, $mailer, 'http://localhost', 'contact@example.fr'))->sendEmail($client);
            self::fail('Une erreur SMTP doit remonter au contrôleur.');
        } catch (\RuntimeException $exception) {
            self::assertSame('SMTP indisponible', $exception->getMessage());
        }

        self::assertSame(Client::TODO, $client->getStatus());
        self::assertFalse($this->firstOfType($persisted, ConsentRequest::class)->isOpen());
        self::assertCount(1, array_filter($persisted, static fn (object $e): bool => $e instanceof AuditEvent && $e->getType() === 'email_failed'));
    }

    public function testEmailWithoutRecipientDoesNotCreateInvitation(): void
    {
        $client = (new Client())->setName('Commerce');
        $em = $this->transactionalEntityManager();
        $em->expects(self::never())->method('persist');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->expectException(\DomainException::class);
        (new InvitationService($em, $mailer, 'http://localhost', 'contact@example.fr'))->sendEmail($client);
    }

    private function transactionalEntityManager(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $callback) use ($em) {
            return $callback($em);
        });
        return $em;
    }

    /** @param list<object> $entities */
    private function firstOfType(array $entities, string $class): object
    {
        foreach ($entities as $entity) {
            if ($entity instanceof $class) {
                return $entity;
            }
        }
        self::fail('Entité absente : '.$class);
    }
}
