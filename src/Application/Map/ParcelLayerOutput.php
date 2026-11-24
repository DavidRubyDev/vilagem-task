<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

final readonly class ParcelLayerOutput
{
    public function __construct(
        public string $tilesetVersion,
        public int $minZoom,
        public int $maxZoom,
        public float $west,
        public float $south,
        public float $east,
        public float $north,
        public string $attributionText,
        public string $attributionUrl,
        public bool $detailsAvailable,
        public bool $searchAvailable,
    ) {}
}
