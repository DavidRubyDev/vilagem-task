<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidTilesetStatistics;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\TilesetStatistics;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Domain\Map\ZoomTileStatistics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TilesetStatisticsTest extends TestCase
{
    #[Test]
    public function it_rejects_having_no_zoom_levels(): void
    {
        $this->expectException(InvalidTilesetStatistics::class);

        new TilesetStatistics();
    }

    #[Test]
    public function it_rejects_a_zoom_level_given_twice(): void
    {
        $this->expectException(InvalidTilesetStatistics::class);
        $this->expectExceptionMessage('12');

        new TilesetStatistics(self::zoom(12, 1, 100), self::zoom(12, 2, 200));
    }

    #[Test]
    public function it_lists_the_zoom_levels_in_ascending_order_whatever_the_given_order(): void
    {
        $statistics = new TilesetStatistics(self::zoom(14, 3, 300), self::zoom(9, 1, 100), self::zoom(12, 2, 200));

        self::assertSame([9, 12, 14], array_map(static fn(ZoomTileStatistics $zoom): int => $zoom->zoom, $statistics->zooms()));
    }

    #[Test]
    public function it_sums_the_tiles_of_all_zoom_levels(): void
    {
        $statistics = new TilesetStatistics(self::zoom(9, 1, 100), self::zoom(12, 2, 200), self::zoom(14, 3, 300));

        self::assertSame(6, $statistics->tileCount());
    }

    #[Test]
    public function it_finds_the_zoom_level_with_the_largest_tile(): void
    {
        $statistics = new TilesetStatistics(self::zoom(9, 1, 100), self::zoom(12, 2, 900), self::zoom(14, 3, 300));

        self::assertSame(12, $statistics->largest()->zoom);
    }

    #[Test]
    public function it_prefers_the_lower_zoom_level_when_the_largest_tiles_are_equal(): void
    {
        $statistics = new TilesetStatistics(self::zoom(14, 3, 900), self::zoom(9, 1, 900), self::zoom(12, 2, 100));

        self::assertSame(9, $statistics->largest()->zoom);
    }

    #[Test]
    public function it_fits_a_budget_when_the_largest_tile_is_within_it(): void
    {
        $statistics = new TilesetStatistics(self::zoom(9, 1, 100), self::zoom(12, 2, 900));

        self::assertTrue($statistics->fitsWithin(new TileSizeBudget(900)));
        self::assertFalse($statistics->fitsWithin(new TileSizeBudget(899)));
    }

    private static function zoom(int $zoom, int $tileCount, int $largestBytes): ZoomTileStatistics
    {
        return new ZoomTileStatistics($zoom, $tileCount, $largestBytes, new TileCoordinate($zoom, 0, 0));
    }
}
