<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidParcelId;
use ParcelMap\Domain\Cadastre\ParcelId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelIdTest extends TestCase
{
    #[Test]
    #[DataProvider('validIds')]
    public function it_accepts_an_id_from_one_to_the_largest_exact_browser_integer(int $id): void
    {
        self::assertSame($id, new ParcelId($id)->value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validIds(): array
    {
        return ['smallest' => [1], 'real' => [1753295604], 'largest' => [9007199254740991]];
    }

    #[Test]
    #[DataProvider('invalidIds')]
    public function it_rejects_an_id_out_of_range(int $id): void
    {
        $this->expectException(InvalidParcelId::class);

        new ParcelId($id);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidIds(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'above 2^53 - 1' => [9007199254740992]];
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        self::assertTrue(new ParcelId(5)->equals(new ParcelId(5)));
        self::assertFalse(new ParcelId(5)->equals(new ParcelId(6)));
    }
}
