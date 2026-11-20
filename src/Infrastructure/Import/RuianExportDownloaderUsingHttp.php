<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ExportNotPublished;
use ParcelMap\Application\Import\RuianExportDownloader;
use ParcelMap\Application\Import\SourceDownloadFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;

/** Downloads monthly RÚIAN VFR municipality files once; a cached file is never fetched again. */
final readonly class RuianExportDownloaderUsingHttp implements RuianExportDownloader
{
    private const string ZIP_SIGNATURE = "PK\x03\x04";

    public function __construct(
        private string $baseUrl,
        private string $cacheDir,
    ) {}

    public function download(MunicipalityCode $municipality, ExportDate $date): DownloadedExport
    {
        $fileName = sprintf('%s_OB_%d_UKSH.xml.zip', str_replace('-', '', $date->toString()), $municipality->value);
        $cachePath = $this->cacheDir . '/' . $fileName;

        if (!is_file($cachePath)) {
            $this->fetch($this->baseUrl . $fileName, $cachePath, $municipality, $date);
        }

        return new DownloadedExport($municipality, $date, $cachePath);
    }

    private function fetch(string $url, string $cachePath, MunicipalityCode $municipality, ExportDate $date): void
    {
        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0o777, true) && !is_dir($this->cacheDir)) {
            throw SourceDownloadFailed::because('cannot create ' . $this->cacheDir . '.');
        }

        $partPath = $cachePath . '.part';
        $status = $this->transfer($url, $partPath);

        if ($status === 404) {
            @unlink($partPath);

            throw ExportNotPublished::for($municipality->value, $date->toString());
        }
        if ($status !== 200 || !$this->looksLikeZip($partPath)) {
            @unlink($partPath);

            throw SourceDownloadFailed::because(
                $status === 200 ? sprintf('%s did not return a zip file.', $url) : sprintf('%s answered HTTP %d.', $url, $status),
            );
        }

        if (!rename($partPath, $cachePath)) {
            @unlink($partPath);

            throw SourceDownloadFailed::because('cannot move the download to ' . $cachePath . '.');
        }
    }

    /**
     * @return int the HTTP status
     * @throws SourceDownloadFailed on a transport error
     */
    private function transfer(string $url, string $partPath): int
    {
        $file = @fopen($partPath, 'w');
        $handle = curl_init($url);
        if ($file === false || $handle === false) {
            throw SourceDownloadFailed::because('cannot start the download of ' . $url . '.');
        }

        curl_setopt_array($handle, [
            CURLOPT_FILE => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FAILONERROR => false,
            CURLOPT_USERAGENT => 'parcel-map-importer',
        ]);

        $succeeded = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        fclose($file);

        if ($succeeded === false) {
            @unlink($partPath);

            throw SourceDownloadFailed::because(sprintf('%s failed: %s', $url, $error));
        }

        return $status;
    }

    private function looksLikeZip(string $path): bool
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }
        $head = fread($handle, 4);
        fclose($handle);

        return $head === self::ZIP_SIGNATURE;
    }
}
