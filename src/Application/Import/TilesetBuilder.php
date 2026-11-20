<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Map\TileSizeBudget;

interface TilesetBuilder
{
    /**
     * @param non-empty-list<ConvertedParcels> $parcels
     * @throws TileBuildFailed
     */
    public function build(array $parcels, TileSizeBudget $budget): BuiltTileset;
}
