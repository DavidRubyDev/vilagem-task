<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use InvalidArgumentException;
use ParcelMap\Application\Import\ConvertedPlaces;
use ParcelMap\Application\Import\SearchIndexBuildFailed;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Search\Address;
use ParcelMap\Domain\Search\DistinctPlaces;
use ParcelMap\Domain\Search\HouseNumber;
use ParcelMap\Domain\Search\HouseNumberKind;
use ParcelMap\Domain\Search\Place;
use ParcelMap\Domain\Search\PlaceKind;
use PDO;
use PDOException;

/**
 * Turns the place file of one municipality into Places. The tables are joined here, in SQL, by attaching the file
 * to the index being built (GDAL joins over the GML files are unusable). Only addresses whose building stands on a
 * parcel of the index are kept, and a street or a part only when one of its addresses is.
 */
final readonly class PlaceFileReader
{
    private const int DECIMALS = 7;

    /**
     * @param array<int, array{float, float, float, float}> $areaBounds west, south, east, north of the parcels per cadastral area code
     * @return list<Place> the distinct places of the municipality in the order kind, code
     * @throws SearchIndexBuildFailed
     */
    public function read(PDO $index, ConvertedPlaces $converted, Coverage $coverage, array $areaBounds): array
    {
        $path = $converted->path;
        if (!is_file($path)) {
            throw SearchIndexBuildFailed::because('cannot read ' . $path . '.');
        }
        $areas = $coverage->areasOf($converted->municipality);
        $municipality = $areas[0]->municipality;

        $places = [];
        $addresses = [];
        $quoted = $index->quote($path);
        // Not inside a transaction: DETACH fails while one is open.
        $index->exec('ATTACH DATABASE ' . $quoted . ' AS m');
        try {
            $addresses = $this->addresses($index, $converted, $municipality->name, $path);
            $streetNames = $this->streets($index, $path);
            $partCodes = [];
            $streetCodes = [];
            $partPoints = [];
            foreach ($addresses as [$place, $partCode, $partName, $streetCode]) {
                $partCodes[$partCode] = $partName;
                $partPoints[$partCode][] = $place->center;
                if ($streetCode !== null) {
                    $streetCodes[$streetCode] = true;
                }
            }
            $places[] = $this->groupPlace($path, PlaceKind::Municipality, $converted, $municipality->name, '', $this->union($areas, $areaBounds));
            foreach ($areas as $area) {
                $places[] = $this->groupPlace(
                    $path,
                    PlaceKind::CadastralArea,
                    $converted,
                    $area->name,
                    $municipality->name,
                    $areaBounds[$area->code->value] ?? null,
                    $area->code->value,
                );
            }
            foreach ($partCodes as $code => $name) {
                $places[] = $this->pointsPlace($path, PlaceKind::MunicipalityPart, $code, $converted, $name, $municipality->name, $partPoints[$code] ?? []);
            }
            foreach ($streetNames as $code => [$name, $points]) {
                if (isset($streetCodes[$code])) {
                    $places[] = $this->pointsPlace($path, PlaceKind::Street, $code, $converted, $name, $municipality->name, $points);
                }
            }
        } catch (PDOException $exception) {
            throw SearchIndexBuildFailed::because(sprintf('cannot read %s: %s', $path, $exception->getMessage()));
        } finally {
            $index->exec('DETACH DATABASE m');
        }
        $places = array_values(array_filter($places, static fn(?Place $place): bool => $place !== null));
        foreach ($addresses as [$place]) {
            $places[] = $place;
        }
        usort($places, static fn(Place $a, Place $b): int => [$a->kind->value, $a->code] <=> [$b->kind->value, $b->code]);

        return DistinctPlaces::keep($places);
    }

    /**
     * @return list<array{Place, int, string, ?int}> the address place, the code and name of its part, the code of its street
     */
    private function addresses(PDO $index, ConvertedPlaces $converted, string $municipalityName, string $path): array
    {
        $rows = $index->query(
            'SELECT a.kod, a.adresnibod, a.cislodomovni, a.cisloorientacni, a.cisloorientacnipismeno,
                    s.typstavebnihoobjektukod, s.identifikacniparcelaid, c.kod AS part_code, c.nazev AS part_name,
                    u.kod AS street_code, u.nazev AS street_name
             FROM m.adresnimista a
             JOIN m.stavebniobjekty s ON s.kod = a.stavebniobjektkod
             JOIN m.castiobci c ON c.kod = s.castobcekod
             LEFT JOIN m.ulice u ON u.kod = a.ulicekod
             JOIN main.parcels p ON p.id = s.identifikacniparcelaid
             WHERE a.adresnibod IS NOT NULL
             ORDER BY a.kod',
        );
        if ($rows === false) {
            throw SearchIndexBuildFailed::because('cannot read the addresses of ' . $path . '.');
        }

        $addresses = [];
        /** @var list<array<string, int|float|string|null>> $fetched */
        $fetched = $rows->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetched as $row) {
            $code = (int) $row['kod'];
            try {
                $number = new HouseNumber(
                    HouseNumberKind::fromCode((int) $row['typstavebnihoobjektukod']),
                    (int) $row['cislodomovni'],
                    $row['cisloorientacni'] === null ? null : (int) $row['cisloorientacni'],
                    ($row['cisloorientacnipismeno'] ?? '') === '' ? null : (string) $row['cisloorientacnipismeno'],
                );
                $address = new Address(
                    $row['street_name'] === null ? null : (string) $row['street_name'],
                    (string) $row['part_name'],
                    $municipalityName,
                    $number,
                    self::point((string) $row['adresnibod']),
                );
                $place = Place::fromAddress($code, $converted->municipality, $address, new ParcelId((int) $row['identifikacniparcelaid']));
            } catch (InvalidArgumentException $exception) {
                throw SearchIndexBuildFailed::invalidPlace($path, 'address', $code, $exception->getMessage());
            }
            $addresses[] = [$place, (int) $row['part_code'], (string) $row['part_name'], $row['street_code'] === null ? null : (int) $row['street_code']];
        }

        return $addresses;
    }

    /**
     * @return array<int, array{string, list<Coordinates>}> name and the points of the line of every street
     */
    private function streets(PDO $index, string $path): array
    {
        $rows = $index->query('SELECT kod, nazev, definicnicara FROM m.ulice');
        if ($rows === false) {
            throw SearchIndexBuildFailed::because('cannot read the streets of ' . $path . '.');
        }

        $streets = [];
        /** @var list<array<string, int|float|string|null>> $fetched */
        $fetched = $rows->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetched as $row) {
            $code = (int) $row['kod'];
            try {
                $streets[$code] = [(string) $row['nazev'], self::linePoints((string) $row['definicnicara'])];
            } catch (InvalidArgumentException $exception) {
                throw SearchIndexBuildFailed::invalidPlace($path, 'street', $code, $exception->getMessage());
            }
        }

        return $streets;
    }

    /**
     * @param list<\ParcelMap\Domain\Cadastre\CadastralArea> $areas
     * @param array<int, array{float, float, float, float}> $areaBounds
     * @return array{float, float, float, float}|null
     */
    private function union(array $areas, array $areaBounds): ?array
    {
        $union = null;
        foreach ($areas as $area) {
            $bounds = $areaBounds[$area->code->value] ?? null;
            if ($bounds === null) {
                continue;
            }
            $union = $union === null
                ? $bounds
                : [min($union[0], $bounds[0]), min($union[1], $bounds[1]), max($union[2], $bounds[2]), max($union[3], $bounds[3])];
        }

        return $union;
    }

    /**
     * @param array{float, float, float, float}|null $bounds null when there are no parcels, so the place cannot be located
     */
    private function groupPlace(string $path, PlaceKind $kind, ConvertedPlaces $converted, string $name, string $context, ?array $bounds, ?int $code = null): ?Place
    {
        if ($bounds === null) {
            return null;
        }
        $code ??= $converted->municipality->value;

        return $this->rectanglePlace(
            $path,
            $kind,
            $code,
            $converted,
            $name,
            $context,
            [new Coordinates(round($bounds[0], self::DECIMALS), round($bounds[1], self::DECIMALS)), new Coordinates(round($bounds[2], self::DECIMALS), round($bounds[3], self::DECIMALS))],
        );
    }

    /**
     * @param list<Coordinates> $points
     */
    private function pointsPlace(string $path, PlaceKind $kind, int $code, ConvertedPlaces $converted, string $name, string $context, array $points): Place
    {
        if ($points === []) {
            throw SearchIndexBuildFailed::invalidPlace($path, self::kindName($kind), $code, 'it has no points.');
        }

        return $this->rectanglePlace($path, $kind, $code, $converted, $name, $context, $points);
    }

    /**
     * @param non-empty-list<Coordinates> $points the rectangle around them is the bounds; a point or a straight line has none
     */
    private function rectanglePlace(string $path, PlaceKind $kind, int $code, ConvertedPlaces $converted, string $name, string $context, array $points): Place
    {
        $longitudes = array_map(static fn(Coordinates $point): float => $point->longitude, $points);
        $latitudes = array_map(static fn(Coordinates $point): float => $point->latitude, $points);
        [$west, $east, $south, $north] = [min($longitudes), max($longitudes), min($latitudes), max($latitudes)];

        try {
            $center = new Coordinates(round(($west + $east) / 2, self::DECIMALS), round(($south + $north) / 2, self::DECIMALS));
            $bounds = $east > $west && $north > $south
                ? new Bounds(new Coordinates($west, $south), new Coordinates($east, $north))
                : null;

            return new Place($kind, $code, $converted->municipality, $name, $context, $center, $bounds, null);
        } catch (InvalidArgumentException $exception) {
            throw SearchIndexBuildFailed::invalidPlace($path, self::kindName($kind), $code, $exception->getMessage());
        }
    }

    private static function kindName(PlaceKind $kind): string
    {
        return match ($kind) {
            PlaceKind::Municipality => 'municipality',
            PlaceKind::CadastralArea => 'cadastral area',
            PlaceKind::MunicipalityPart => 'municipality part',
            PlaceKind::Street => 'street',
            PlaceKind::Address => 'address',
        };
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function point(string $wkt): Coordinates
    {
        if (preg_match('~^POINT\s*\(\s*(\S+)\s+(\S+)\s*\)$~', trim($wkt), $parts) !== 1 || !is_numeric($parts[1]) || !is_numeric($parts[2])) {
            throw new InvalidArgumentException(sprintf('the point "%s" is not well-known text.', $wkt));
        }

        return new Coordinates(round((float) $parts[1], self::DECIMALS), round((float) $parts[2], self::DECIMALS));
    }

    /**
     * @return non-empty-list<Coordinates>
     * @throws InvalidArgumentException
     */
    private static function linePoints(string $wkt): array
    {
        if (preg_match('~^(?:MULTI)?LINESTRING\s*\((.*)\)$~s', trim($wkt), $match) !== 1) {
            throw new InvalidArgumentException(sprintf('the line "%s" is not well-known text.', substr($wkt, 0, 40)));
        }
        $points = [];
        foreach (preg_split('~[(),]+~', $match[1]) ?: [] as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $numbers = preg_split('~\s+~', $pair) ?: [];
            if (count($numbers) < 2 || !is_numeric($numbers[0]) || !is_numeric($numbers[1])) {
                throw new InvalidArgumentException(sprintf('the line position "%s" is not "longitude latitude".', $pair));
            }
            $points[] = new Coordinates(round((float) $numbers[0], self::DECIMALS), round((float) $numbers[1], self::DECIMALS));
        }

        return $points === [] ? throw new InvalidArgumentException('the line has no positions.') : $points;
    }
}
