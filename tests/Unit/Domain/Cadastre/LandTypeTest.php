<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidLandType;
use ParcelMap\Domain\Cadastre\LandType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LandTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('positiveCodes')]
    public function it_keeps_any_positive_code(int $code): void
    {
        self::assertSame($code, new LandType($code)->code);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function positiveCodes(): array
    {
        return ['known' => [2], 'unknown' => [99]];
    }

    #[Test]
    #[DataProvider('nonPositiveCodes')]
    public function it_rejects_a_code_below_one(int $code): void
    {
        $this->expectException(InvalidLandType::class);

        new LandType($code);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveCodes(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    #[Test]
    public function it_knows_exactly_the_published_land_type_codes(): void
    {
        $known = array_values(array_filter(range(1, 20), static fn(int $code): bool => new LandType($code)->isKnown()));

        self::assertSame([2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14], $known);
        self::assertFalse(new LandType(99)->isKnown());
    }

    #[Test]
    public function it_equals_by_code(): void
    {
        self::assertTrue(new LandType(13)->equals(new LandType(13)));
        self::assertFalse(new LandType(13)->equals(new LandType(14)));
    }
}
