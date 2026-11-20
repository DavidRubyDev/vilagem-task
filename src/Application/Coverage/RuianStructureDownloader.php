<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

use ParcelMap\Domain\Cadastre\ExportDate;

interface RuianStructureDownloader
{
    /**
     * @throws StructureNotPublished
     * @throws StructureDownloadFailed
     */
    public function download(ExportDate $date): DownloadedStructure;
}
