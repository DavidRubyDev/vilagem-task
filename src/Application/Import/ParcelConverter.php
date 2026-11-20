<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\CadastralArea;

interface ParcelConverter
{
    /**
     * @param non-empty-list<CadastralArea> $areas the covered areas of the export's municipality
     * @throws ConversionFailed
     * @throws CadastralAreaNotInExport the first given area without parcels
     */
    public function convert(DownloadedExport $export, array $areas): ConvertedParcels;
}
