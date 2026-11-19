<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** The raster tile layer under the data. */
final readonly class BaseMap
{
    public function __construct(
        public TileUrlTemplate $tileUrlTemplate,
        public int $tileSize,
        public int $maxZoom,
        public Attribution $attribution,
    ) {
        if ($tileSize !== 256 && $tileSize !== 512) {
            throw InvalidBaseMap::unsupportedTileSize($tileSize);
        }
        if ($maxZoom < 0 || $maxZoom > 24) {
            throw InvalidBaseMap::maxZoomOutOfRange($maxZoom);
        }
    }

    public function equals(self $other): bool
    {
        return $this->tileUrlTemplate->equals($other->tileUrlTemplate)
            && $this->tileSize === $other->tileSize
            && $this->maxZoom === $other->maxZoom
            && $this->attribution->equals($other->attribution);
    }
}
