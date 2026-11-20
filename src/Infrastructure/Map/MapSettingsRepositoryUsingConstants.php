<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Map;

use ParcelMap\Domain\Map\MapSettingsRepository;
use ParcelMap\Domain\Map\Attribution;
use ParcelMap\Domain\Map\BaseMap;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\MapView;
use ParcelMap\Domain\Map\TileUrlTemplate;
use ParcelMap\Domain\Map\ZoomLevel;

/**
 * The configuration adapter. The values live here as constants because nothing else supplies
 * them yet; later tasks may replace parts with values from data (e.g. MBTiles metadata).
 */
final readonly class MapSettingsRepositoryUsingConstants implements MapSettingsRepository
{
    private const float CENTER_LONGITUDE = 15.351653;
    private const float CENTER_LATITUDE = 50.437045;
    private const float INITIAL_ZOOM = 12.0;
    private const string TILE_URL_TEMPLATE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    private const int TILE_SIZE = 256;
    private const int MAX_ZOOM = 19;
    private const string ATTRIBUTION_TEXT = '© OpenStreetMap contributors';
    private const string ATTRIBUTION_URL = 'https://www.openstreetmap.org/copyright';

    public function initialView(): MapView
    {
        return new MapView(
            new Coordinates(self::CENTER_LONGITUDE, self::CENTER_LATITUDE),
            new ZoomLevel(self::INITIAL_ZOOM),
        );
    }

    public function baseMap(): BaseMap
    {
        return new BaseMap(
            new TileUrlTemplate(self::TILE_URL_TEMPLATE),
            self::TILE_SIZE,
            self::MAX_ZOOM,
            new Attribution(self::ATTRIBUTION_TEXT, self::ATTRIBUTION_URL),
        );
    }
}
