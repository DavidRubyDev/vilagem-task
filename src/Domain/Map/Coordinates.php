<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** WGS84 position in degrees. Longitude comes first, as in MapLibre and GeoJSON. */
final readonly class Coordinates
{
    public function __construct(
        public float $longitude,
        public float $latitude,
    ) {
        if (!is_finite($longitude) || !is_finite($latitude)) {
            throw InvalidCoordinates::notFinite();
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw InvalidCoordinates::longitudeOutOfRange($longitude);
        }
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw InvalidCoordinates::latitudeOutOfRange($latitude);
        }
    }

    public function equals(self $other): bool
    {
        return $this->longitude === $other->longitude && $this->latitude === $other->latitude;
    }
}
