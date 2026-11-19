<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\Attribution;
use ParcelMap\Domain\Map\BaseMap;
use ParcelMap\Domain\Map\InvalidBaseMap;
use ParcelMap\Domain\Map\TileUrlTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BaseMapTest extends TestCase
{
    #[Test]
    #[DataProvider('validTileSizes')]
    public function it_accepts_tile_sizes_256_and_512(int $tileSize): void
    {
        self::assertSame($tileSize, $this->baseMap(tileSize: $tileSize)->tileSize);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validTileSizes(): array
    {
        return ['size 256' => [256], 'size 512' => [512]];
    }

    #[Test]
    public function it_rejects_an_unsupported_tile_size(): void
    {
        $this->expectException(InvalidBaseMap::class);

        $this->baseMap(tileSize: 300);
    }

    #[Test]
    #[DataProvider('validMaxZooms')]
    public function it_accepts_max_zoom_from_0_to_24(int $maxZoom): void
    {
        self::assertSame($maxZoom, $this->baseMap(maxZoom: $maxZoom)->maxZoom);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validMaxZooms(): array
    {
        return ['zoom 0' => [0], 'zoom 24' => [24]];
    }

    #[Test]
    #[DataProvider('invalidMaxZooms')]
    public function it_rejects_max_zoom_out_of_range(int $maxZoom): void
    {
        $this->expectException(InvalidBaseMap::class);

        $this->baseMap(maxZoom: $maxZoom);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidMaxZooms(): array
    {
        return ['below' => [-1], 'above' => [25]];
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        self::assertTrue($this->baseMap()->equals($this->baseMap()));
        self::assertFalse($this->baseMap()->equals($this->baseMap(maxZoom: 18)));
        self::assertFalse($this->baseMap()->equals($this->baseMap(tileSize: 512)));
    }

    private function baseMap(int $tileSize = 256, int $maxZoom = 19): BaseMap
    {
        return new BaseMap(
            new TileUrlTemplate('https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
            $tileSize,
            $maxZoom,
            new Attribution('© OpenStreetMap contributors', 'https://www.openstreetmap.org/copyright'),
        );
    }
}
