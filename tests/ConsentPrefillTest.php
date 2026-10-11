<?php
namespace App\Tests;

use App\Entity\Client;
use App\Service\ConsentPrefill;
use PHPUnit\Framework\TestCase;

final class ConsentPrefillTest extends TestCase
{
    public function testPrefillsClientNameWithoutSelectingAChoice(): void
    {
        $data = ConsentPrefill::forClient((new Client())->setName('Mat VD'));

        self::assertSame('Mat', $data->firstName);
        self::assertSame('VD', $data->lastName);
        self::assertSame('', $data->signatoryRole);
        self::assertNull($data->choice);
    }

    public function testKeepsRemainderOfNameTogetherForCorrection(): void
    {
        $data = ConsentPrefill::forClient((new Client())->setName('Jean Claude Van Damme'));

        self::assertSame('Jean', $data->firstName);
        self::assertSame('Claude Van Damme', $data->lastName);
    }

    public function testDoesNotGuessWhenNameCannotBeSplit(): void
    {
        $data = ConsentPrefill::forClient((new Client())->setName('Boulangerie'));

        self::assertSame('', $data->firstName);
        self::assertSame('', $data->lastName);
    }

    public function testDoesNotOverflowFields(): void
    {
        $data = ConsentPrefill::forClient((new Client())->setName('Jean '.str_repeat('X', 121)));

        self::assertSame('', $data->firstName);
        self::assertSame('', $data->lastName);
    }
}
