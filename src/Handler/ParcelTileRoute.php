<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

/** The one place that knows the shape of tile URLs. */
final class ParcelTileRoute
{
    public const string PATTERN = '/tiles/parcels/{version}/{z}/{x}/{y}.pbf';

    private function __construct() {}

    /** The pattern for one tileset version, keeping {z}/{x}/{y} for the map library to fill in. */
    public static function urlTemplate(string $version): string
    {
        return str_replace('{version}', $version, self::PATTERN);
    }
}
