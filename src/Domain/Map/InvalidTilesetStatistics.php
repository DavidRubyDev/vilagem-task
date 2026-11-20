<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidTilesetStatistics extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('The tileset statistics must contain at least one zoom level.');
    }

    public static function duplicateZoom(int $zoom): self
    {
        return new self(sprintf('The zoom level %d is listed more than once.', $zoom));
    }

    public static function noTiles(int $zoom): self
    {
        return new self(sprintf('The zoom level %d must have at least one tile.', $zoom));
    }

    public static function emptyTile(int $zoom): self
    {
        return new self(sprintf('The largest tile of the zoom level %d must have at least one byte.', $zoom));
    }

    public static function tileOnAnotherZoom(int $zoom, int $tileZoom): self
    {
        return new self(sprintf('The largest tile of the zoom level %d is on the zoom level %d.', $zoom, $tileZoom));
    }
}
