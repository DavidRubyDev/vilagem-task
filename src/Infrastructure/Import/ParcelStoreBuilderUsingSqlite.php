<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use InvalidArgumentException;
use JsonException;
use ParcelMap\Application\Import\BuiltParcelStore;
use ParcelMap\Application\Import\CodeList;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\ParcelStoreBuilder;
use ParcelMap\Application\Import\ParcelStoreBuildFailed;
use ParcelMap\Application\Import\UnknownCode;
use ParcelMap\Domain\Cadastre\Area;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\LandType;
use ParcelMap\Domain\Cadastre\LandUse;
use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Coordinates;
use PDO;
use PDOException;
use PDOStatement;
use Pdo\Sqlite;
use Throwable;

/**
 * Builds the parcel store, a SQLite file with the details of every parcel, from the converted GeoJSON files.
 * Every line becomes a domain Parcel first, so invalid source data fails the import, not a click on the map.
 * Boundaries are stored zlib-compressed (for the district 46 MB instead of 110 MB); a lookup by id decompresses one.
 */
final readonly class ParcelStoreBuilderUsingSqlite implements ParcelStoreBuilder
{
    private const int SQLITE_CONSTRAINT = 19;

    public function __construct(private string $workDir) {}

    public function build(array $parcels, Coverage $coverage): BuiltParcelStore
    {
        if (!is_dir($this->workDir) && !@mkdir($this->workDir, 0o777, true) && !is_dir($this->workDir)) {
            throw ParcelStoreBuildFailed::because('cannot create ' . $this->workDir . '.');
        }
        $path = $this->workDir . '/parcels.sqlite';
        if (is_file($path) && !@unlink($path)) {
            throw ParcelStoreBuildFailed::because('cannot delete ' . $path . '.');
        }

        try {
            return new BuiltParcelStore($path, $this->write($path, $parcels, $coverage));
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception instanceof ParcelStoreBuildFailed
                ? $exception
                : ParcelStoreBuildFailed::because(sprintf('cannot write %s: %s', $path, $exception->getMessage()));
        }
    }

    /**
     * @param non-empty-list<ConvertedParcels> $parcels
     * @return list<UnknownCode>
     */
    private function write(string $path, array $parcels, Coverage $coverage): array
    {
        $pdo = new Sqlite('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // A build file that is not yet published is simply rebuilt if the import dies, so no journal and no fsync.
        $pdo->exec('PRAGMA journal_mode = OFF');
        $pdo->exec('PRAGMA synchronous = OFF');
        $this->createSchema($pdo);
        $this->insertAreas($pdo, $coverage);

        $insert = $pdo->prepare(
            'INSERT INTO parcels (id, cadastral_area_code, kind, base_number, subdivision, land_type, land_use, area, boundary) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $unknown = [];
        $pdo->beginTransaction();
        foreach ($parcels as $converted) {
            $this->insertFile($converted->path, $coverage, $insert, $unknown);
        }
        $pdo->commit();

        return $this->unknownCodes($unknown);
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec(
            'CREATE TABLE cadastral_areas (
                code INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                municipality_code INTEGER NOT NULL,
                municipality_name TEXT NOT NULL
            )',
        );
        $pdo->exec(
            'CREATE TABLE parcels (
                id INTEGER PRIMARY KEY,
                cadastral_area_code INTEGER NOT NULL REFERENCES cadastral_areas (code),
                kind INTEGER NOT NULL,
                base_number INTEGER NOT NULL,
                subdivision INTEGER,
                land_type INTEGER NOT NULL,
                land_use INTEGER,
                area INTEGER NOT NULL,
                boundary BLOB NOT NULL
            )',
        );
    }

    private function insertAreas(PDO $pdo, Coverage $coverage): void
    {
        $insert = $pdo->prepare('INSERT INTO cadastral_areas (code, name, municipality_code, municipality_name) VALUES (?, ?, ?, ?)');
        foreach ($coverage->areas() as $area) {
            $insert->execute([$area->code->value, $area->name, $area->municipality->code->value, $area->municipality->name]);
        }
    }

    /**
     * @param array<string, int> $unknown parcels per "<list>:<code>"
     */
    private function insertFile(string $path, Coverage $coverage, PDOStatement $insert, array &$unknown): void
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw ParcelStoreBuildFailed::because('cannot read ' . $path . '.');
        }

        try {
            for ($line = 1; ($text = fgets($handle)) !== false; ++$line) {
                if (trim($text) === '') {
                    continue;
                }
                $this->insertLine($text, $coverage, $insert, $unknown, $path, $line);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, int> $unknown
     */
    private function insertLine(string $text, Coverage $coverage, PDOStatement $insert, array &$unknown, string $path, int $line): void
    {
        try {
            $parcel = $this->parcel($text, $coverage);
            $insert->execute([
                $parcel->id->value,
                $parcel->cadastralArea->code->value,
                $parcel->number->kind->value,
                $parcel->number->baseNumber,
                $parcel->number->subdivision,
                $parcel->landType->code,
                $parcel->landUse?->code,
                $parcel->area->squareMetres,
                $this->compressedBoundary($parcel->boundary),
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ParcelStoreBuildFailed::invalidParcel($path, $line, $exception->getMessage());
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::SQLITE_CONSTRAINT) {
                throw ParcelStoreBuildFailed::invalidParcel($path, $line, 'duplicate parcel id: ' . $exception->getMessage());
            }

            throw $exception;
        }

        if (!$parcel->landType->isKnown()) {
            $key = CodeList::LandType->value . ':' . $parcel->landType->code;
            $unknown[$key] = ($unknown[$key] ?? 0) + 1;
        }
        if ($parcel->landUse !== null && !$parcel->landUse->isKnown()) {
            $key = CodeList::LandUse->value . ':' . $parcel->landUse->code;
            $unknown[$key] = ($unknown[$key] ?? 0) + 1;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function parcel(string $text, Coverage $coverage): Parcel
    {
        try {
            $feature = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidParcelLine::notJson($exception->getMessage());
        }
        if (!is_array($feature) || !is_array($feature['properties'] ?? null)) {
            throw InvalidParcelLine::notAnObject();
        }
        $properties = $feature['properties'];

        $landUse = self::optionalInteger($properties, 'landUse');

        return new Parcel(
            new ParcelId(self::integer($properties, 'id')),
            $coverage->area(new CadastralAreaCode(self::integer($properties, 'areaCode'))),
            new ParcelNumber(
                ParcelKind::fromCode(self::integer($properties, 'kind')),
                self::integer($properties, 'baseNumber'),
                self::optionalInteger($properties, 'subdivision'),
            ),
            new LandType(self::integer($properties, 'landType')),
            $landUse === null ? null : new LandUse($landUse),
            new Area(self::integer($properties, 'area')),
            ParcelBoundary::fromRings($this->rings($feature['geometry'] ?? null)),
        );
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    private static function integer(array $properties, string $name): int
    {
        $value = $properties[$name] ?? null;

        return is_int($value) ? $value : throw InvalidParcelLine::missingInteger($name);
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    private static function optionalInteger(array $properties, string $name): ?int
    {
        return array_key_exists($name, $properties) ? self::integer($properties, $name) : null;
    }

    /**
     * @param mixed $geometry decoded JSON, so untyped until this method has checked it
     * @return list<list<Coordinates>>
     */
    private function rings(mixed $geometry): array
    {
        if (!is_array($geometry) || ($geometry['type'] ?? null) !== 'Polygon' || !is_array($geometry['coordinates'] ?? null)) {
            throw InvalidParcelLine::notAPolygon();
        }

        $rings = [];
        foreach ($geometry['coordinates'] as $ring) {
            if (!is_array($ring)) {
                throw InvalidParcelLine::notAPolygon();
            }
            $positions = [];
            foreach ($ring as $position) {
                if (!is_array($position) || !is_numeric($position[0] ?? null) || !is_numeric($position[1] ?? null)) {
                    throw InvalidParcelLine::notAPolygon();
                }
                $positions[] = new Coordinates((float) $position[0], (float) $position[1]);
            }
            $rings[] = $positions;
        }

        return $rings;
    }

    private function compressedBoundary(ParcelBoundary $boundary): string
    {
        $rings = array_map(
            static fn(array $ring): array => array_map(
                static fn(Coordinates $position): array => [$position->longitude, $position->latitude],
                $ring,
            ),
            $boundary->rings(),
        );

        return gzcompress(json_encode($rings, JSON_THROW_ON_ERROR))
            ?: throw ParcelStoreBuildFailed::because('cannot compress a parcel boundary.');
    }

    /**
     * @param array<string, int> $unknown
     * @return list<UnknownCode>
     */
    private function unknownCodes(array $unknown): array
    {
        $codes = [];
        foreach ($unknown as $key => $count) {
            [$list, $code] = explode(':', $key);
            $codes[] = new UnknownCode(CodeList::from($list), (int) $code, $count);
        }
        usort($codes, static fn(UnknownCode $a, UnknownCode $b): int => [$a->list === CodeList::LandUse, $a->code] <=> [$b->list === CodeList::LandUse, $b->code]);

        return $codes;
    }
}
