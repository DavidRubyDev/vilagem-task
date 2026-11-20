<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;

interface RuianExportDownloader
{
    /**
     * @throws ExportNotPublished
     * @throws SourceDownloadFailed
     */
    public function download(MunicipalityCode $municipality, ExportDate $date): DownloadedExport;
}
