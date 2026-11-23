<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

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
use ParcelMap\Handler\GetMapConfigurationHandler;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Tests\Fake\MapSettingsRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMapConfigurationHandlerTest extends TestCase
{
    private const string VERSION = '20261120093000';

    #[Test]
    public function it_tells_where_to_ask_for_the_details_of_a_parcel(): void
    {
        $parcels = $this->parcels(new ParcelRepositoryUsingMemory(new TilesetVersion(self::VERSION)));

        self::assertSame('/api/parcels/' . self::VERSION . '/{id}', $parcels['parcelUrlTemplate']);
        self::assertSame('/tiles/parcels/' . self::VERSION . '/{z}/{x}/{y}.pbf', $parcels['tileUrlTemplate']);
    }

    #[Test]
    public function it_sends_no_parcel_url_template_when_the_details_are_not_imported(): void
    {
        $parcels = $this->parcels(new ParcelRepositoryUsingMemory(null));

        self::assertArrayHasKey('parcelUrlTemplate', $parcels);
        self::assertNull($parcels['parcelUrlTemplate']);
        self::assertSame('/tiles/parcels/' . self::VERSION . '/{z}/{x}/{y}.pbf', $parcels['tileUrlTemplate']);
    }

    /**
     * @return array<string, mixed>
     */
    private function parcels(ParcelRepositoryUsingMemory $store): array
    {
        $tileset = new ParcelTileset(
            new TilesetVersion(self::VERSION),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            9,
            16,
        );
        $handler = new GetMapConfigurationHandler(new GetMapConfigurationUseCase(
            new MapSettingsRepositoryUsingMemory(
                new MapView(new Coordinates(15.35, 50.43), new ZoomLevel(12)),
                new BaseMap(new TileUrlTemplate('https://tiles.example.org/{z}/{x}/{y}.png'), 256, 19, new Attribution('Credit', 'https://example.org')),
            ),
            new ParcelTilesetRepositoryUsingMemory($tileset),
            $store,
        ));

        $body = json_decode($handler->handle(new Request('GET', '/api/map-config'))->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['parcels']);

        /** @var array<string, mixed> $parcels */
        $parcels = $body['parcels'];

        return $parcels;
    }
}
