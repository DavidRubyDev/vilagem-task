<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** The tiles of one zoom level of a tileset: how many, and the largest one (gzip-compressed bytes). */
final readonly class ZoomTileStatistics
{
    public function __construct(
        public int $zoom,
        public int $tileCount,
        public int $largestTileBytes,
        public TileCoordinate $largestTile,
    ) {
        if ($tileCount < 1) {
            throw InvalidTilesetStatistics::noTiles($zoom);
        }
        if ($largestTileBytes < 1) {
            throw InvalidTilesetStatistics::emptyTile($zoom);
        }
        if ($largestTile->zoom !== $zoom) {
            throw InvalidTilesetStatistics::tileOnAnotherZoom($zoom, $largestTile->zoom);
        }
    }
}
