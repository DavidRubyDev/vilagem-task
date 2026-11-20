<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

interface ParcelTilesetRepository
{
    /**
     * @throws ParcelDataNotImported
     * @throws ParcelTilesetUnreadable
     */
    public function current(): ParcelTileset;

    /**
     * @return VectorTile an empty tile when the tileset has no tile at the coordinate
     * @throws ParcelDataNotImported
     * @throws ParcelTilesetUnreadable
     */
    public function tile(TileCoordinate $coordinate): VectorTile;
}
