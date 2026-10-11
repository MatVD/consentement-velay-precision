<?php
namespace App\Tests;

use App\Entity\Client;
use App\Service\ConsentPrefill;
use PHPUnit\Framework\TestCase;

final class ConsentPrefillTest extends TestCase
{
    public function testNeverInfersPersonalIdentityFromCompanyName(): void
    {
        $data = ConsentPrefill::forClient((new Client())->setName('Boulangerie Martin'));
        self::assertSame('', $data->firstName);
        self::assertSame('', $data->lastName);
        self::assertSame('', $data->signatoryRole);
        self::assertNull($data->choice);
    }

    public function testPrefillsOnlyExplicitlyRecordedContact(): void
    {
        $client = (new Client())->setName('Boulangerie Martin')
            ->setContactFirstName('Anne')
            ->setContactLastName('Martin')
            ->setContactRole('Gérante');
        $data = ConsentPrefill::forClient($client);

        self::assertSame('Anne', $data->firstName);
        self::assertSame('Martin', $data->lastName);
        self::assertSame('Gérante', $data->signatoryRole);
        self::assertNull($data->choice);
    }

    public function testBlankOptionalContactsAreNormalized(): void
    {
        $client = (new Client())->setName('Client')
            ->setContactFirstName('  ')
            ->setContactLastName(null)
            ->setContactRole('');
        self::assertNull($client->getContactFirstName());
        self::assertNull($client->getContactLastName());
        self::assertNull($client->getContactRole());
    }

    public function testDoesNotInventMissingFieldsOfPartialContact(): void
    {
        $client = (new Client())->setName('Boulangerie Martin')->setContactLastName('Martin');
        $data = ConsentPrefill::forClient($client);
        self::assertSame('', $data->firstName);
        self::assertSame('Martin', $data->lastName);
        self::assertNull($data->choice);
    }
}
