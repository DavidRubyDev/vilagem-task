<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** A tile in the XYZ scheme (y counted from the top), as web maps request it. */
final readonly class TileCoordinate
{
    private const int MAX_ZOOM = 24;

    public function __construct(
        public int $zoom,
        public int $x,
        public int $y,
    ) {
        if ($zoom < 0 || $zoom > self::MAX_ZOOM) {
            throw InvalidTileCoordinate::outOfRange($zoom, $x, $y);
        }
        $tilesPerAxis = 1 << $zoom;
        if ($x < 0 || $x >= $tilesPerAxis || $y < 0 || $y >= $tilesPerAxis) {
            throw InvalidTileCoordinate::outOfRange($zoom, $x, $y);
        }
    }

    public function equals(self $other): bool
    {
        return $this->zoom === $other->zoom && $this->x === $other->x && $this->y === $other->y;
    }
}
