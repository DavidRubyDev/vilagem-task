<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\ConvertedPlaces;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\PlaceConverter;
use ParcelMap\Infrastructure\Process\ProcessNotStarted;
use ParcelMap\Infrastructure\Process\ProcessRunner;

/**
 * Writes the parts, streets, buildings and address points of one municipality into one SQLite file with GDAL: one
 * run per municipality, layers in file order, so the GDAL GML driver reads the file in one sequential pass. GDAL
 * joins are unusable on these files, so the index builder joins the tables itself. Never use INTERLEAVED_LAYERS,
 * which writes nothing.
 */
final readonly class PlaceConverterUsingOgr2ogr implements PlaceConverter
{
    public function __construct(
        private ProcessRunner $runner,
        private string $workDir,
    ) {}

    public function convert(DownloadedExport $export): ConvertedPlaces
    {
        $directory = $this->workDir . '/places';
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ConversionFailed::because('cannot create ' . $directory . '.');
        }
        $output = sprintf('%s/%d.sqlite', $directory, $export->municipality->value);
        if (is_file($output) && !@unlink($output)) {
            throw ConversionFailed::because('cannot delete ' . $output . '.');
        }

        try {
            $result = $this->runner->run([
                'ogr2ogr',
                '-f', 'SQLite',
                $output,
                sprintf('/vsizip/%s/%s', $export->path, basename($export->path, '.zip')),
                'CastiObci', 'Ulice', 'StavebniObjekty', 'AdresniMista',
                '-t_srs', 'EPSG:4326',
                '-oo', 'READ_MODE=SEQUENTIAL_LAYERS',
                '-lco', 'FORMAT=WKT',
            ]);
        } catch (ProcessNotStarted $exception) {
            throw ConversionFailed::because($exception->getMessage());
        }
        if ($result->exitCode !== 0) {
            @unlink($output);

            throw ConversionFailed::because(sprintf('ogr2ogr failed for %s: %s', $export->path, trim($result->stderr)));
        }

        return new ConvertedPlaces($export->municipality, $output);
    }
}
