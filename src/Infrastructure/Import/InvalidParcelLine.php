<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use InvalidArgumentException;

/** A line of a converted GeoJSON file that does not describe a parcel the store can hold. */
final class InvalidParcelLine extends InvalidArgumentException
{
    public static function notJson(string $reason): self
    {
        return new self('the line is not JSON: ' . $reason);
    }

    public static function notAnObject(): self
    {
        return new self('the line is not a GeoJSON feature.');
    }

    public static function missingInteger(string $property): self
    {
        return new self(sprintf('the property "%s" is missing or not an integer.', $property));
    }

    public static function notAPolygon(): self
    {
        return new self('the geometry is not a Polygon with [longitude, latitude] positions.');
    }

    public static function duplicateId(int $id): self
    {
        return new self(sprintf('the parcel id %d occurs twice.', $id));
    }
}
