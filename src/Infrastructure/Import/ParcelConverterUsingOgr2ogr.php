<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use JsonException;
use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\CadastralAreaNotInExport;
use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ParcelConverter;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Infrastructure\Process\ProcessRunner;

/**
 * Reads the parcels of the covered cadastral areas of one municipality straight from the zipped VFR file with
 * GDAL (one run per municipality, because every run parses the whole file) and writes them as GeoJSON lines in
 * WGS84, keeping the id, the land type (DruhPozemkuKod) and the cadastral area code (to count the parcels per
 * area; the tile builder drops it). GDAL picks the S-JTSK to WGS84 transformation itself.
 */
final readonly class ParcelConverterUsingOgr2ogr implements ParcelConverter
{
    public function __construct(
        private ProcessRunner $runner,
        private string $workDir,
    ) {}

    public function convert(DownloadedExport $export, array $areas): ConvertedParcels
    {
        $directory = $this->workDir . '/municipalities';
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ConversionFailed::because('cannot create ' . $directory . '.');
        }
        $output = sprintf('%s/%d.geojsonl', $directory, $export->municipality->value);

        $result = $this->runner->run([
            'ogr2ogr',
            '-f', 'GeoJSONSeq',
            $output,
            sprintf('/vsizip/%s/%s', $export->path, basename($export->path, '.zip')),
            '-sql', sprintf(
                'SELECT Id AS id, DruhPozemkuKod AS landType, KatastralniUzemiKod AS areaCode, OriginalniHranice '
                . 'FROM Parcely WHERE KatastralniUzemiKod IN (%s)',
                implode(', ', array_map(static fn(CadastralArea $area): int => $area->code->value, $areas)),
            ),
            '-dialect', 'OGRSQL',
            '-t_srs', 'EPSG:4326',
            '-lco', 'COORDINATE_PRECISION=7',
        ]);
        if ($result->exitCode !== 0) {
            @unlink($output);

            throw ConversionFailed::because(sprintf('ogr2ogr failed for %s: %s', $export->path, trim($result->stderr)));
        }

        try {
            $counts = $this->countParcelsPerArea($output);

            return new ConvertedParcels($export->municipality, $output, $this->areaCounts($export, $areas, $counts));
        } catch (ConversionFailed|CadastralAreaNotInExport $exception) {
            @unlink($output);

            throw $exception;
        }
    }

    /**
     * @return array<int, int> parcels per cadastral area code
     */
    private function countParcelsPerArea(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw ConversionFailed::because('cannot read ' . $path . '.');
        }

        $counts = [];
        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                $area = $this->areaCodeOf($line, $path);
                $counts[$area] = ($counts[$area] ?? 0) + 1;
            }
        } finally {
            fclose($handle);
        }

        return $counts;
    }

    private function areaCodeOf(string $line, string $path): int
    {
        try {
            $feature = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversionFailed::because(sprintf('%s contains a line that is not JSON: %s', $path, $exception->getMessage()));
        }

        $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
        $area = is_array($properties) ? ($properties['areaCode'] ?? null) : null;
        if (!is_int($area)) {
            throw ConversionFailed::because(sprintf('%s contains a parcel without a cadastral area code.', $path));
        }

        return $area;
    }

    /**
     * @param non-empty-list<CadastralArea> $areas
     * @param array<int, int> $counts
     * @return non-empty-list<AreaParcelCount>
     */
    private function areaCounts(DownloadedExport $export, array $areas, array $counts): array
    {
        $areaCounts = [];
        foreach ($areas as $area) {
            $count = $counts[$area->code->value] ?? 0;
            if ($count === 0) {
                throw CadastralAreaNotInExport::for($area->code->value, $export->municipality->value, $export->date->toString());
            }
            $areaCounts[] = new AreaParcelCount($area->code, $count);
        }

        return $areaCounts;
    }
}
