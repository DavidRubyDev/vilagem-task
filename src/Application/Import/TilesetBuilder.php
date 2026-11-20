<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

interface TilesetBuilder
{
    /**
     * @param non-empty-list<ConvertedParcels> $parcels
     * @throws TileBuildFailed
     */
    public function build(array $parcels): BuiltTileset;
}
