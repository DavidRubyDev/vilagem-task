<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

/**
 * Facts about tests/Fixtures/data/parcels/<version>.sqlite (see tests/Fixtures/README.md).
 */
final class FixtureParcelStore
{
    public const int BUILDING_ID = 1753295604;
    public const string BUILDING_NUMBER = 'st. 72';
    public const int BUILDING_AREA = 455;
    public const int BUILDING_RING_POSITIONS = 32;
    public const float BUILDING_FIRST_LONGITUDE = 15.3509779;
    public const float BUILDING_FIRST_LATITUDE = 50.4377444;

    public const int LAND_WITH_HOLES_ID = 1761441604;
    public const string LAND_WITH_HOLES_NUMBER = '1171';
    public const int LAND_WITH_HOLES_AREA = 13077;
    public const int LAND_WITH_HOLES_LAND_USE = 17;

    public const int ROBOUSY_ID = 99225194010;
    public const string ROBOUSY_NUMBER = '867/121';
    public const int ROBOUSY_AREA = 725;

    public const int AREA_CODE_JICIN = 659541;
    public const int AREA_CODE_ROBOUSY = 740225;
    public const int MUNICIPALITY_CODE_JICIN = 572659;

    private function __construct() {}

    public static function file(): string
    {
        return dirname(__DIR__) . '/Fixtures/data/parcels/' . FixtureTileset::VERSION . '.sqlite';
    }
}
