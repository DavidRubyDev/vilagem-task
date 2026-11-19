<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** The camera position the map opens with. */
final readonly class MapView
{
    public function __construct(
        public Coordinates $center,
        public ZoomLevel $zoom,
    ) {}

    public function equals(self $other): bool
    {
        return $this->center->equals($other->center) && $this->zoom->equals($other->zoom);
    }
}
