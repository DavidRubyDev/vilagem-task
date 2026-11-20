<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\CadastralArea;

interface ParcelConverter
{
    /**
     * @throws ConversionFailed
     * @throws CadastralAreaNotInExport
     */
    public function convert(DownloadedExport $export, CadastralArea $area): ConvertedParcels;
}
