<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;

/** A RÚIAN export file on disk; the path is opaque to the use case. */
final readonly class DownloadedExport
{
    public function __construct(
        public MunicipalityCode $municipality,
        public ExportDate $date,
        public string $path,
        public bool $fromCache,
    ) {}
}
