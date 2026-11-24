<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

use PDO;

/**
 * Writes a place file the way PlaceConverterUsingOgr2ogr does: four SQLite tables with GDAL's lower-case column
 * names and WKT geometries in longitude/latitude order. Tests use it to give the index builder exactly the places
 * they need.
 */
final class PlaceFile
{
    private function __construct() {}

    /**
     * @param list<array{code: int, name: string, point: string}> $parts point as "lon lat"
     * @param list<array{code: int, name: string, line: string}> $streets line as the pairs of a MULTILINESTRING: "lon lat,lon lat"
     * @param list<array{code: int, type: int, part: int, parcel: int}> $buildings
     * @param list<array{code: int, number: int, orientation?: int|null, letter?: string|null, building: int, street?: int|null, point?: string|null}> $addresses point as "lon lat"
     */
    public static function write(string $path, int $municipality, array $parts = [], array $streets = [], array $buildings = [], array $addresses = []): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE castiobci (ogc_fid INTEGER PRIMARY KEY, kod INTEGER, nazev TEXT, obeckod INTEGER, definicnibod TEXT)');
        $pdo->exec('CREATE TABLE ulice (ogc_fid INTEGER PRIMARY KEY, kod INTEGER, nazev TEXT, obeckod INTEGER, definicnicara TEXT)');
        $pdo->exec('CREATE TABLE stavebniobjekty (ogc_fid INTEGER PRIMARY KEY, kod INTEGER, typstavebnihoobjektukod INTEGER, castobcekod INTEGER, identifikacniparcelaid INTEGER)');
        $pdo->exec(
            'CREATE TABLE adresnimista (ogc_fid INTEGER PRIMARY KEY, kod INTEGER, cislodomovni INTEGER, cisloorientacni INTEGER,'
            . ' cisloorientacnipismeno TEXT, stavebniobjektkod INTEGER, ulicekod INTEGER, adresnibod TEXT)',
        );

        $insert = $pdo->prepare('INSERT INTO castiobci (kod, nazev, obeckod, definicnibod) VALUES (?, ?, ?, ?)');
        foreach ($parts as $part) {
            $insert->execute([$part['code'], $part['name'], $municipality, 'POINT (' . $part['point'] . ')']);
        }
        $insert = $pdo->prepare('INSERT INTO ulice (kod, nazev, obeckod, definicnicara) VALUES (?, ?, ?, ?)');
        foreach ($streets as $street) {
            $insert->execute([$street['code'], $street['name'], $municipality, 'MULTILINESTRING ((' . $street['line'] . '))']);
        }
        $insert = $pdo->prepare('INSERT INTO stavebniobjekty (kod, typstavebnihoobjektukod, castobcekod, identifikacniparcelaid) VALUES (?, ?, ?, ?)');
        foreach ($buildings as $building) {
            $insert->execute([$building['code'], $building['type'], $building['part'], $building['parcel']]);
        }
        $insert = $pdo->prepare(
            'INSERT INTO adresnimista (kod, cislodomovni, cisloorientacni, cisloorientacnipismeno, stavebniobjektkod, ulicekod, adresnibod)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($addresses as $address) {
            $point = $address['point'] ?? null;
            $insert->execute([
                $address['code'],
                $address['number'],
                $address['orientation'] ?? null,
                $address['letter'] ?? null,
                $address['building'],
                $address['street'] ?? null,
                $point === null ? null : 'POINT (' . $point . ')',
            ]);
        }
    }
}
