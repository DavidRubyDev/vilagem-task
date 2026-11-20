<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

final readonly class MapConfigurationOutput
{
    public function __construct(
        public MapViewOutput $initialView,
        public BaseMapOutput $baseMap,
        public ?ParcelLayerOutput $parcels,
    ) {}
}
