<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Coverage;

use InvalidArgumentException;
use ParcelMap\Application\Coverage\DistrictAreaExtractor;
use ParcelMap\Application\Coverage\DownloadedStructure;
use ParcelMap\Application\Coverage\StructureReadFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\DistrictCode;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Process\ProcessRunner;

/**
 * Reads the municipalities and cadastral areas of a district straight from the zipped state structure file
 * with GDAL. Two queries, because GDAL's SQL cannot filter one layer on a field of a joined layer: first the
 * codes and names of the municipalities of the district (Obce), then the cadastral areas with a digital map of those
 * municipalities (KatastralniUzemi). Areas without a digital map have no parcel polygons and are left out.
 */
final readonly class DistrictAreaExtractorUsingOgr2ogr implements DistrictAreaExtractor
{
    public function __construct(private ProcessRunner $runner) {}

    public function extract(DownloadedStructure $structure, DistrictCode $district): array
    {
        $municipalities = $this->query(
            $structure,
            sprintf('SELECT Kod, Nazev FROM Obce WHERE OkresKod = %d', $district->value),
            ['Kod', 'Nazev'],
        );
        if ($municipalities === []) {
            return [];
        }

        $municipalityNames = [];
        foreach ($municipalities as [$code, $name]) {
            if (!ctype_digit($code)) {
                throw StructureReadFailed::because(sprintf('the code of the municipality "%s" is not a number.', $name));
            }
            $municipalityNames[(int) $code] = $name;
        }

        $rows = $this->query(
            $structure,
            sprintf(
                "SELECT Kod, Nazev, ObecKod FROM KatastralniUzemi WHERE ExistujeDigitalniMapa = 'true' AND ObecKod IN (%s)",
                implode(',', array_keys($municipalityNames)),
            ),
            ['Kod', 'Nazev', 'ObecKod'],
        );

        return array_map(fn(array $row): CadastralArea => $this->area($row, $municipalityNames), $rows);
    }

    /**
     * @param list<string> $header
     * @return list<list<string>> the data rows
     */
    private function query(DownloadedStructure $structure, string $sql, array $header): array
    {
        $result = $this->runner->run([
            'ogr2ogr',
            '-f', 'CSV',
            '/vsistdout/',
            sprintf('/vsizip/%s/%s', $structure->path, basename($structure->path, '.zip')),
            '-dialect', 'OGRSQL',
            '-sql', $sql,
        ]);
        if ($result->exitCode !== 0) {
            throw StructureReadFailed::because(sprintf('ogr2ogr failed for %s: %s', $structure->path, trim($result->stderr)));
        }

        return $this->parse($result->stdout, $header, $structure->path);
    }

    /**
     * @param list<string> $header
     * @return list<list<string>>
     */
    private function parse(string $csv, array $header, string $path): array
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw StructureReadFailed::because('cannot read the output of ogr2ogr.');
        }
        fwrite($stream, $csv);
        rewind($stream);

        $firstRow = fgetcsv($stream, escape: '');
        if ($firstRow !== $header) {
            fclose($stream);

            throw StructureReadFailed::because(sprintf('%s: expected the columns "%s".', $path, implode(',', $header)));
        }

        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count($row) !== count($header)) {
                fclose($stream);

                throw StructureReadFailed::because(sprintf('%s: expected %d fields in a row, found %d.', $path, count($header), count($row)));
            }
            $rows[] = array_map(static fn(?string $field): string => (string) $field, $row);
        }
        fclose($stream);

        return $rows;
    }

    /**
     * @param list<string> $row Kod, Nazev, ObecKod
     * @param array<int, string> $municipalityNames by municipality code
     */
    private function area(array $row, array $municipalityNames): CadastralArea
    {
        [$code, $name, $municipality] = $row;
        if (!ctype_digit($code) || !ctype_digit($municipality)) {
            throw StructureReadFailed::because(sprintf('the codes of the cadastral area "%s" are not numbers.', $name));
        }

        if (!isset($municipalityNames[(int) $municipality])) {
            throw StructureReadFailed::because(sprintf('the cadastral area "%s" belongs to the unknown municipality %s.', $name, $municipality));
        }

        try {
            return new CadastralArea(
                new CadastralAreaCode((int) $code),
                $name,
                new Municipality(new MunicipalityCode((int) $municipality), $municipalityNames[(int) $municipality]),
            );
        } catch (InvalidArgumentException $exception) {
            throw StructureReadFailed::because($exception->getMessage());
        }
    }
}
