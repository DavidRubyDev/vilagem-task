<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final readonly class ImportParcelsOutput
{
    /**
     * @param string $exportDate Y-m-d
     * @param list<ImportedAreaOutput> $areas
     * @param list<ZoomTilesOutput> $zoomLevels ascending by zoom
     * @param list<UnknownCodeOutput> $unknownCodes in the order of the parcel store builder
     */
    public function __construct(
        public string $exportDate,
        public string $tilesetVersion,
        public array $areas,
        public int $totalParcels,
        public int $tileSizeBudgetBytes,
        public array $zoomLevels,
        public array $unknownCodes,
    ) {}
}
