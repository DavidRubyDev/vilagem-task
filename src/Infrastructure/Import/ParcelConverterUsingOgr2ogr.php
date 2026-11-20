<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\CadastralAreaNotInExport;
use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ParcelConverter;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Infrastructure\Process\ProcessRunner;

/**
 * Reads the parcels of one cadastral area straight from the zipped VFR file with GDAL and writes them as
 * GeoJSON lines in WGS84, keeping only the id and the land type (DruhPozemkuKod). GDAL picks the
 * S-JTSK to WGS84 transformation itself.
 */
final readonly class ParcelConverterUsingOgr2ogr implements ParcelConverter
{
    public function __construct(
        private ProcessRunner $runner,
        private string $workDir,
    ) {}

    public function convert(DownloadedExport $export, CadastralArea $area): ConvertedParcels
    {
        $directory = $this->workDir . '/parcels';
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ConversionFailed::because('cannot create ' . $directory . '.');
        }
        $output = sprintf('%s/%d.geojsonl', $directory, $area->code->value);

        $result = $this->runner->run([
            'ogr2ogr',
            '-f', 'GeoJSONSeq',
            $output,
            sprintf('/vsizip/%s/%s', $export->path, basename($export->path, '.zip')),
            '-sql', sprintf(
                'SELECT Id AS id, DruhPozemkuKod AS landType, OriginalniHranice FROM Parcely WHERE KatastralniUzemiKod = %d',
                $area->code->value,
            ),
            '-dialect', 'OGRSQL',
            '-t_srs', 'EPSG:4326',
            '-lco', 'COORDINATE_PRECISION=7',
        ]);
        if ($result->exitCode !== 0) {
            @unlink($output);

            throw ConversionFailed::because(sprintf('ogr2ogr failed for %s: %s', $export->path, trim($result->stderr)));
        }

        $count = $this->countFeatures($output);
        if ($count === 0) {
            @unlink($output);

            throw CadastralAreaNotInExport::for($area->code->value, $export->municipality->value, $export->date->toString());
        }

        return new ConvertedParcels($area->code, $output, $count);
    }

    private function countFeatures(string $path): int
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines === false ? 0 : count($lines);
    }
}
