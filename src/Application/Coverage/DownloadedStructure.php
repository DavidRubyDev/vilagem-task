<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

use ParcelMap\Domain\Cadastre\ExportDate;

/** The RÚIAN state structure file on disk; the path is opaque to the use case. */
final readonly class DownloadedStructure
{
    public function __construct(
        public ExportDate $date,
        public string $path,
        public bool $fromCache,
    ) {}
}
