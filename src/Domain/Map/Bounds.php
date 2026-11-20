<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** A rectangle on the map, given by its south-west and north-east corners. */
final readonly class Bounds
{
    public function __construct(
        public Coordinates $southWest,
        public Coordinates $northEast,
    ) {
        if ($southWest->longitude >= $northEast->longitude || $southWest->latitude >= $northEast->latitude) {
            throw InvalidBounds::notOrdered();
        }
    }

    public function equals(self $other): bool
    {
        return $this->southWest->equals($other->southWest) && $this->northEast->equals($other->northEast);
    }
}
