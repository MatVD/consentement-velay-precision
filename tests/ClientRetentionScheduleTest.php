<?php
namespace App\Tests;

use App\Entity\Client;
use App\Entity\ClientLifecycleEvent;
use App\Service\ClientRetentionSchedule;
use App\Service\ConsentPolicy;
use PHPUnit\Framework\TestCase;

final class ClientRetentionScheduleTest extends TestCase
{
    public function testThreeYearTimelineStartsWithManuallyEnteredContractEnd(): void
    {
        $dates = (new ClientRetentionSchedule())->deadlines(
            new \DateTimeImmutable('2026-10-12', new \DateTimeZone('Europe/Paris'))
        );
        self::assertSame('2027-10-12', $dates['anonymize_after']->format('Y-m-d'));
        self::assertSame('2029-10-12', $dates['delete_after']->format('Y-m-d'));
    }

    public function testManualArchiveNeverChangesTheYesOrNoChoice(): void
    {
        $client = (new Client())->setName('Commerce exemple');
        $today = new \DateTimeImmutable('2026-10-12', new \DateTimeZone('UTC'));
        $client->changeStatus(Client::NO, $today);
        $client->archiveContract($today, $today);
        self::assertFalse($client->isActive());
        self::assertSame(Client::NO, $client->getStatus());
        self::assertSame('Client archivé', (new ClientLifecycleEvent(ClientLifecycleEvent::ARCHIVED))->getLabel());
        self::assertSame('Client anonymisé', (new ClientLifecycleEvent(ClientLifecycleEvent::ANONYMIZED))->getLabel());
        self::assertSame('Client supprimé', (new ClientLifecycleEvent(ClientLifecycleEvent::DELETED))->getLabel());
    }

    public function testRefusalMentionsPaperSignatureAndVersionsThePresentedText(): void
    {
        self::assertSame('2026-10-12-v2', ConsentPolicy::VERSION);
        self::assertStringContainsString('signature manuscrite sur papier', ConsentPolicy::TEXT);
    }

    public function testCannotArchiveTheSameClientTwice(): void
    {
        $client = (new Client())->setName('Commerce');
        $date = new \DateTimeImmutable('2026-10-12', new \DateTimeZone('UTC'));
        $client->archiveContract($date, $date);
        $this->expectException(\DomainException::class);
        $client->archiveContract($date, $date);
    }
}
