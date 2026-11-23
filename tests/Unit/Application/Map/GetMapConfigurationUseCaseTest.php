<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Map;

use ParcelMap\Application\Map\GetMapConfigurationUseCase;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\Attribution;
use ParcelMap\Domain\Map\BaseMap;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\MapView;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Map\TileUrlTemplate;
use ParcelMap\Domain\Map\ZoomLevel;
use ParcelMap\Tests\Fake\MapSettingsRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMapConfigurationUseCaseTest extends TestCase
{
    #[Test]
    public function it_maps_the_settings_to_the_output_field_by_field(): void
    {
        $settings = new MapSettingsRepositoryUsingMemory(
            new MapView(new Coordinates(longitude: 15.5, latitude: 50.25), new ZoomLevel(11.5)),
            new BaseMap(
                new TileUrlTemplate('https://tiles.example.org/{z}/{x}/{y}.png'),
                512,
                17,
                new Attribution('Example credit', 'https://example.org/credit'),
            ),
        );

        $output = new GetMapConfigurationUseCase($settings, new ParcelTilesetRepositoryUsingMemory(null), new ParcelRepositoryUsingMemory(null))->execute();

        self::assertSame(15.5, $output->initialView->centerLongitude);
        self::assertSame(50.25, $output->initialView->centerLatitude);
        self::assertSame(11.5, $output->initialView->zoom);
        self::assertSame('https://tiles.example.org/{z}/{x}/{y}.png', $output->baseMap->tileUrlTemplate);
        self::assertSame(512, $output->baseMap->tileSize);
        self::assertSame(17, $output->baseMap->maxZoom);
        self::assertSame('Example credit', $output->baseMap->attributionText);
        self::assertSame('https://example.org/credit', $output->baseMap->attributionUrl);
    }

    #[Test]
    public function it_has_no_parcel_layer_when_no_data_is_imported(): void
    {
        $output = $this->useCase(new ParcelTilesetRepositoryUsingMemory(null))->execute();

        self::assertNull($output->parcels);
    }

    #[Test]
    public function it_maps_the_parcel_tileset_into_the_parcel_layer(): void
    {
        $tileset = new ParcelTileset(
            new TilesetVersion('20261120093000'),
            ExportDate::fromString('2025-12-31'),
            new Bounds(new Coordinates(15.1, 50.2), new Coordinates(15.3, 50.4)),
            9,
            16,
        );

        $parcels = $this->useCase(new ParcelTilesetRepositoryUsingMemory($tileset))->execute()->parcels;

        self::assertNotNull($parcels);
        self::assertSame('20261120093000', $parcels->tilesetVersion);
        self::assertSame(9, $parcels->minZoom);
        self::assertSame(16, $parcels->maxZoom);
        self::assertSame(15.1, $parcels->west);
        self::assertSame(50.2, $parcels->south);
        self::assertSame(15.3, $parcels->east);
        self::assertSame(50.4, $parcels->north);
        self::assertSame('ČÚZK, 2025', $parcels->attributionText);
        self::assertStringStartsWith('https://cuzk.gov.cz/', $parcels->attributionUrl);
    }

    #[Test]
    public function it_offers_parcel_details_when_the_store_of_the_current_version_is_published(): void
    {
        $parcels = $this->useCase(
            new ParcelTilesetRepositoryUsingMemory(self::tileset()),
            new ParcelRepositoryUsingMemory(new TilesetVersion('20261120093000')),
        )->execute()->parcels;

        self::assertNotNull($parcels);
        self::assertTrue($parcels->detailsAvailable);
    }

    #[Test]
    public function it_does_not_offer_parcel_details_when_the_store_of_the_current_version_is_missing(): void
    {
        $parcels = $this->useCase(
            new ParcelTilesetRepositoryUsingMemory(self::tileset()),
            new ParcelRepositoryUsingMemory(new TilesetVersion('19990101000000')),
        )->execute()->parcels;

        self::assertNotNull($parcels);
        self::assertFalse($parcels->detailsAvailable);
    }

    private static function tileset(): ParcelTileset
    {
        return new ParcelTileset(
            new TilesetVersion('20261120093000'),
            ExportDate::fromString('2025-12-31'),
            new Bounds(new Coordinates(15.1, 50.2), new Coordinates(15.3, 50.4)),
            9,
            16,
        );
    }

    private function useCase(ParcelTilesetRepositoryUsingMemory $tilesets, ?ParcelRepositoryUsingMemory $parcels = null): GetMapConfigurationUseCase
    {
        return new GetMapConfigurationUseCase(
            new MapSettingsRepositoryUsingMemory(
                new MapView(new Coordinates(longitude: 15.5, latitude: 50.25), new ZoomLevel(11.5)),
                new BaseMap(
                    new TileUrlTemplate('https://tiles.example.org/{z}/{x}/{y}.png'),
                    512,
                    17,
                    new Attribution('Example credit', 'https://example.org/credit'),
                ),
            ),
            $tilesets,
            $parcels ?? new ParcelRepositoryUsingMemory(null),
        );
    }
}
