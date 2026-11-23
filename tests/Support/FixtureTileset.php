<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

/**
 * Facts about tests/Fixtures/data/tiles/parcels.mbtiles (see tests/Fixtures/README.md).
 */
final class FixtureTileset
{
    public const string VERSION = '20261123140206';
    public const string EXPORT_DATE = '2026-10-31';
    public const float WEST = 15.350516;
    public const float SOUTH = 50.432327;
    public const float EAST = 15.403251;
    public const float NORTH = 50.437799;
    public const int MIN_ZOOM = 9;
    public const int MAX_ZOOM = 16;

    private function __construct() {}

    public static function file(): string
    {
        return dirname(__DIR__) . '/Fixtures/data/tiles/parcels.mbtiles';
    }
}
