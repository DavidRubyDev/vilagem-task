<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

final readonly class MapViewOutput
{
    public function __construct(
        public float $centerLongitude,
        public float $centerLatitude,
        public float $zoom,
    ) {}
}
