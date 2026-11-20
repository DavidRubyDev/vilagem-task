<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Http;

/**
 * Downloads a zip file into a cache path once; a cached file is never fetched again. The body goes to a
 * `.part` file first and is renamed only after it was checked, so the cache never holds a partial download.
 */
final readonly class ZipFileDownloader
{
    private const string ZIP_SIGNATURE = "PK\x03\x04";

    /**
     * @throws RemoteFileNotFound
     * @throws RemoteFileUnavailable
     */
    public function download(string $url, string $cachePath): FetchedFile
    {
        if (is_file($cachePath)) {
            return new FetchedFile($cachePath, true);
        }

        $directory = dirname($cachePath);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw RemoteFileUnavailable::because('cannot create ' . $directory . '.');
        }

        $partPath = $cachePath . '.part';
        $status = $this->transfer($url, $partPath);

        if ($status === 404) {
            @unlink($partPath);

            throw RemoteFileNotFound::at($url);
        }
        if ($status !== 200 || !$this->looksLikeZip($partPath)) {
            @unlink($partPath);

            throw RemoteFileUnavailable::because(
                $status === 200 ? sprintf('%s did not return a zip file.', $url) : sprintf('%s answered HTTP %d.', $url, $status),
            );
        }

        if (!rename($partPath, $cachePath)) {
            @unlink($partPath);

            throw RemoteFileUnavailable::because('cannot move the download to ' . $cachePath . '.');
        }

        return new FetchedFile($cachePath, false);
    }

    /**
     * @return int the HTTP status
     * @throws RemoteFileUnavailable on a transport error
     */
    private function transfer(string $url, string $partPath): int
    {
        $file = @fopen($partPath, 'w');
        $handle = curl_init($url);
        if ($file === false || $handle === false) {
            throw RemoteFileUnavailable::because('cannot start the download of ' . $url . '.');
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

            throw RemoteFileUnavailable::because(sprintf('%s failed: %s', $url, $error));
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
