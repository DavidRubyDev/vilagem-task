<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidTileCoordinate;
use ParcelMap\Domain\Map\TileCoordinate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TileCoordinateTest extends TestCase
{
    #[Test]
    #[DataProvider('valid')]
    public function it_accepts_coordinates_inside_the_tile_grid(int $zoom, int $x, int $y): void
    {
        $coordinate = new TileCoordinate($zoom, $x, $y);

        self::assertSame([$zoom, $x, $y], [$coordinate->zoom, $coordinate->x, $coordinate->y]);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function valid(): array
    {
        return [
            'the world tile' => [0, 0, 0],
            'deepest zoom, last tile' => [24, 16777215, 16777215],
            'map centre' => [12, 2222, 1381],
        ];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_coordinates_outside_the_tile_grid(int $zoom, int $x, int $y): void
    {
        $this->expectException(InvalidTileCoordinate::class);

        new TileCoordinate($zoom, $x, $y);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function invalid(): array
    {
        return [
            'negative zoom' => [-1, 0, 0],
            'zoom above 24' => [25, 0, 0],
            'x equals 2^zoom' => [12, 4096, 0],
            'y equals 2^zoom' => [12, 0, 4096],
            'negative x' => [12, -1, 0],
            'negative y' => [12, 0, -1],
        ];
    }
}
