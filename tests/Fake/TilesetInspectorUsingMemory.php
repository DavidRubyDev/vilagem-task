<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TilesetInspectionFailed;
use ParcelMap\Application\Import\TilesetInspector;
use ParcelMap\Domain\Map\TilesetStatistics;

final readonly class TilesetInspectorUsingMemory implements TilesetInspector
{
    public function __construct(private TilesetStatistics|TilesetInspectionFailed $statistics) {}

    public function inspect(BuiltTileset $tileset): TilesetStatistics
    {
        if ($this->statistics instanceof TilesetInspectionFailed) {
            throw $this->statistics;
        }

        return $this->statistics;
    }
}
