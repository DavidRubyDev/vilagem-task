<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidCoordinates extends InvalidArgumentException
{
    public static function longitudeOutOfRange(float $longitude): self
    {
        return new self(sprintf('Longitude must be between -180 and 180, %s given.', $longitude));
    }

    public static function latitudeOutOfRange(float $latitude): self
    {
        return new self(sprintf('Latitude must be between -90 and 90, %s given.', $latitude));
    }

    public static function notFinite(): self
    {
        return new self('Longitude and latitude must be finite numbers.');
    }
}
