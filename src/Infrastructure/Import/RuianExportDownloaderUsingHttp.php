<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ExportNotPublished;
use ParcelMap\Application\Import\RuianExportDownloader;
use ParcelMap\Application\Import\SourceDownloadFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Http\RemoteFileNotFound;
use ParcelMap\Infrastructure\Http\RemoteFileUnavailable;
use ParcelMap\Infrastructure\Http\ZipFileDownloader;

/** Downloads monthly RÚIAN VFR municipality files once; a cached file is never fetched again. */
final readonly class RuianExportDownloaderUsingHttp implements RuianExportDownloader
{
    public function __construct(
        private ZipFileDownloader $downloader,
        private string $baseUrl,
        private string $cacheDir,
    ) {}

    public function download(MunicipalityCode $municipality, ExportDate $date): DownloadedExport
    {
        $fileName = sprintf('%s_OB_%d_UKSH.xml.zip', str_replace('-', '', $date->toString()), $municipality->value);

        try {
            $file = $this->downloader->download($this->baseUrl . $fileName, $this->cacheDir . '/' . $fileName);
        } catch (RemoteFileNotFound) {
            throw ExportNotPublished::for($municipality->value, $date->toString());
        } catch (RemoteFileUnavailable $exception) {
            throw SourceDownloadFailed::because($exception->getMessage());
        }

        return new DownloadedExport($municipality, $date, $file->path);
    }
}
