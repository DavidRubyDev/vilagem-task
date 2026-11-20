<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\InvalidParcelTileset;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\TilesetVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelTilesetTest extends TestCase
{
    #[Test]
    public function it_credits_cuzk_with_the_year_of_the_export_and_links_the_conditions(): void
    {
        $attribution = self::tileset(9, 16)->attribution();

        self::assertSame('ČÚZK, 2026', $attribution->text);
        self::assertSame(
            'https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx',
            $attribution->url,
        );
    }

    #[Test]
    public function it_knows_which_zoom_levels_it_covers(): void
    {
        $tileset = self::tileset(9, 16);

        self::assertFalse($tileset->coversZoom(8));
        self::assertTrue($tileset->coversZoom(9));
        self::assertTrue($tileset->coversZoom(16));
        self::assertFalse($tileset->coversZoom(17));
    }

    #[Test]
    #[DataProvider('invalidZooms')]
    public function it_rejects_an_invalid_zoom_range(int $min, int $max): void
    {
        $this->expectException(InvalidParcelTileset::class);

        self::tileset($min, $max);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function invalidZooms(): array
    {
        return [
            'min above max' => [10, 9],
            'negative min' => [-1, 9],
            'max above 24' => [9, 25],
        ];
    }

    private static function tileset(int $minZoom, int $maxZoom): ParcelTileset
    {
        return new ParcelTileset(
            new TilesetVersion('20261120093000'),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            $minZoom,
            $maxZoom,
        );
    }
}
