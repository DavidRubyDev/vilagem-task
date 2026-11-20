<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ExportNotPublished;
use ParcelMap\Application\Import\RuianExportDownloader;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;

final class RuianExportDownloaderUsingMemory implements RuianExportDownloader
{
    /** @var list<string> "<municipality> <date>" of every download attempt, in order */
    public array $attempts = [];

    /**
     * @param list<string> $publishedDates dates (Y-m-d) that are published
     * @param list<int> $cachedMunicipalities municipalities whose file is already cached
     */
    public function __construct(
        private readonly array $publishedDates,
        private readonly array $cachedMunicipalities = [],
    ) {}

    public function download(MunicipalityCode $municipality, ExportDate $date): DownloadedExport
    {
        $this->attempts[] = $municipality->value . ' ' . $date->toString();
        if (!in_array($date->toString(), $this->publishedDates, true)) {
            throw ExportNotPublished::for($municipality->value, $date->toString());
        }

        return new DownloadedExport($municipality, $date, 'memory://' . $municipality->value . '/' . $date->toString(), in_array($municipality->value, $this->cachedMunicipalities, true));
    }
}
