<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Coverage;

use ParcelMap\Application\Coverage\DownloadedStructure;
use ParcelMap\Application\Coverage\RuianStructureDownloader;
use ParcelMap\Application\Coverage\StructureDownloadFailed;
use ParcelMap\Application\Coverage\StructureNotPublished;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Infrastructure\Http\RemoteFileNotFound;
use ParcelMap\Infrastructure\Http\RemoteFileUnavailable;
use ParcelMap\Infrastructure\Http\ZipFileDownloader;

/** Downloads the monthly RÚIAN state structure file (ST_UZSZ) once; a cached file is never fetched again. */
final readonly class RuianStructureDownloaderUsingHttp implements RuianStructureDownloader
{
    public function __construct(
        private ZipFileDownloader $downloader,
        private string $baseUrl,
        private string $cacheDir,
    ) {}

    public function download(ExportDate $date): DownloadedStructure
    {
        $fileName = sprintf('%s_ST_UZSZ.xml.zip', str_replace('-', '', $date->toString()));

        try {
            $file = $this->downloader->download($this->baseUrl . $fileName, $this->cacheDir . '/' . $fileName);
        } catch (RemoteFileNotFound) {
            throw StructureNotPublished::for($date->toString());
        } catch (RemoteFileUnavailable $exception) {
            throw StructureDownloadFailed::because($exception->getMessage());
        }

        return new DownloadedStructure($date, $file->path, $file->fromCache);
    }
}
