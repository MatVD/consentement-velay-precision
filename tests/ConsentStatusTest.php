<?php
namespace App\Tests;

use App\Entity\Client;
use PHPUnit\Framework\TestCase;

final class ConsentStatusTest extends TestCase
{
    public function testTransitions(): void
    {
        $client = (new Client())->setName('Test');
        self::assertSame('À faire', $client->getStatusLabel());
        $client->changeStatus(Client::SENT, new \DateTimeImmutable());
        self::assertSame('Mail envoyé', $client->getStatusLabel());
        $client->changeStatus(Client::YES, new \DateTimeImmutable());
        self::assertSame('Oui', $client->getStatusLabel());
        $this->expectException(\DomainException::class);
        $client->changeStatus(Client::NO, new \DateTimeImmutable());
    }
}
