<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

/** The one place that knows the shape of parcel detail URLs. */
final class ParcelDetailRoute
{
    public const string PATTERN = '/api/parcels/{version}/{id}';

    private function __construct() {}

    /** The pattern for one tileset version, keeping {id} for the frontend to fill in. */
    public static function urlTemplate(string $version): string
    {
        return str_replace('{version}', $version, self::PATTERN);
    }
}
