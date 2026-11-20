<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Map\TilesetStatistics;

interface TilesetInspector
{
    /**
     * @throws TilesetInspectionFailed
     */
    public function inspect(BuiltTileset $tileset): TilesetStatistics;
}
