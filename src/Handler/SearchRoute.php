<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

/** The one place that knows the shape of search URLs. */
final class SearchRoute
{
    public const string PATTERN = '/api/search/{version}';

    private function __construct() {}

    public static function url(string $version): string
    {
        return str_replace('{version}', $version, self::PATTERN);
    }
}
