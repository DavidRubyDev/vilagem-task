<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidBaseMap extends InvalidArgumentException
{
    public static function unsupportedTileSize(int $tileSize): self
    {
        return new self(sprintf('Tile size must be 256 or 512, %d given.', $tileSize));
    }

    public static function maxZoomOutOfRange(int $maxZoom): self
    {
        return new self(sprintf('Max zoom must be between 0 and 24, %d given.', $maxZoom));
    }
}
