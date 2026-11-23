<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidLandUse;
use ParcelMap\Domain\Cadastre\LandUse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LandUseTest extends TestCase
{
    #[Test]
    #[DataProvider('positiveCodes')]
    public function it_keeps_any_positive_code(int $code): void
    {
        self::assertSame($code, new LandUse($code)->code);
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
        $this->expectException(InvalidLandUse::class);

        new LandUse($code);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveCodes(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    #[Test]
    public function it_knows_exactly_the_published_land_use_codes(): void
    {
        $known = array_values(array_filter(range(1, 40), static fn(int $code): bool => new LandUse($code)->isKnown()));

        self::assertSame(range(1, 30), $known);
        self::assertFalse(new LandUse(31)->isKnown());
    }

    #[Test]
    public function it_equals_by_code(): void
    {
        self::assertTrue(new LandUse(17)->equals(new LandUse(17)));
        self::assertFalse(new LandUse(17)->equals(new LandUse(26)));
    }
}
