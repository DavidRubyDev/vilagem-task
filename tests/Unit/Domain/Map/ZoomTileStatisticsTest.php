<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidTilesetStatistics;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\ZoomTileStatistics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZoomTileStatisticsTest extends TestCase
{
    #[Test]
    public function it_holds_the_figures_of_a_zoom_level(): void
    {
        $statistics = new ZoomTileStatistics(12, 2, 595, new TileCoordinate(12, 2222, 1381));

        self::assertSame(12, $statistics->zoom);
        self::assertSame(2, $statistics->tileCount);
        self::assertSame(595, $statistics->largestTileBytes);
        self::assertSame(2222, $statistics->largestTile->x);
    }

    #[Test]
    public function it_rejects_a_zoom_level_without_tiles(): void
    {
        $this->expectException(InvalidTilesetStatistics::class);

        new ZoomTileStatistics(12, 0, 595, new TileCoordinate(12, 2222, 1381));
    }

    #[Test]
    public function it_rejects_a_largest_tile_without_bytes(): void
    {
        $this->expectException(InvalidTilesetStatistics::class);

        new ZoomTileStatistics(12, 2, 0, new TileCoordinate(12, 2222, 1381));
    }

    #[Test]
    public function it_rejects_a_largest_tile_of_another_zoom_level(): void
    {
        $this->expectException(InvalidTilesetStatistics::class);
        $this->expectExceptionMessage('12');

        new ZoomTileStatistics(12, 2, 595, new TileCoordinate(13, 4445, 2762));
    }
}
