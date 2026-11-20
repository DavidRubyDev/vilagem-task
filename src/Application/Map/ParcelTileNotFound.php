<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

use RuntimeException;

final class ParcelTileNotFound extends RuntimeException
{
    public static function noDataImported(): self
    {
        return new self('No parcel data has been imported.');
    }

    public static function unknownVersion(string $version): self
    {
        return new self(sprintf('There is no parcel tileset with the version "%s".', $version));
    }

    public static function invalidCoordinate(int $zoom, int $x, int $y): self
    {
        return new self(sprintf('The tile %d/%d/%d does not exist.', $zoom, $x, $y));
    }
}
