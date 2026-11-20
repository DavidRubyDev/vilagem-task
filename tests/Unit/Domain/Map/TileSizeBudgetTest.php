<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidTileSizeBudget;
use ParcelMap\Domain\Map\TileSizeBudget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TileSizeBudgetTest extends TestCase
{
    #[Test]
    #[DataProvider('notPositive')]
    public function it_rejects_a_budget_below_one_byte(int $bytes): void
    {
        $this->expectException(InvalidTileSizeBudget::class);
        $this->expectExceptionMessage((string) $bytes);

        new TileSizeBudget($bytes);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function notPositive(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    #[Test]
    public function it_allows_a_tile_up_to_the_limit_and_not_above(): void
    {
        $budget = new TileSizeBudget(1000);

        self::assertTrue($budget->allows(999));
        self::assertTrue($budget->allows(1000));
        self::assertFalse($budget->allows(1001));
    }

    #[Test]
    public function the_smooth_map_budget_is_200_kilobytes(): void
    {
        self::assertSame(200_000, TileSizeBudget::smoothMap()->maxBytes);
    }

    #[Test]
    public function it_is_equal_to_a_budget_of_the_same_size(): void
    {
        self::assertTrue(new TileSizeBudget(1000)->equals(new TileSizeBudget(1000)));
        self::assertFalse(new TileSizeBudget(1000)->equals(new TileSizeBudget(2000)));
    }
}
