<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use InvalidArgumentException;
use JsonException;
use ParcelMap\Application\Import\BuiltSearchIndex;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\SearchIndexBuilder;
use ParcelMap\Application\Import\SearchIndexBuildFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Search\SearchText;
use PDO;
use PDOException;
use PDOStatement;
use Pdo\Sqlite;
use Throwable;

/**
 * Builds the search index, a SQLite file with the number and bounds of every parcel, from the converted GeoJSON files.
 * The index keeps its own slim parcel table, so search needs neither the parcel store nor the tiles.
 * Every line becomes domain objects first, so invalid source data fails the import, not a search.
 */
final readonly class SearchIndexBuilderUsingSqlite implements SearchIndexBuilder
{
    private const int SQLITE_CONSTRAINT = 19;

    public function __construct(private string $workDir) {}

    public function build(array $parcels, Coverage $coverage): BuiltSearchIndex
    {
        if (!is_dir($this->workDir) && !@mkdir($this->workDir, 0o777, true) && !is_dir($this->workDir)) {
            throw SearchIndexBuildFailed::because('cannot create ' . $this->workDir . '.');
        }
        $path = $this->workDir . '/search.sqlite';
        if (is_file($path) && !@unlink($path)) {
            throw SearchIndexBuildFailed::because('cannot delete ' . $path . '.');
        }

        try {
            $this->write($path, $parcels, $coverage);
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception instanceof SearchIndexBuildFailed
                ? $exception
                : SearchIndexBuildFailed::because(sprintf('cannot write %s: %s', $path, $exception->getMessage()));
        }

        return new BuiltSearchIndex($path);
    }

    /**
     * @param non-empty-list<ConvertedParcels> $parcels
     */
    private function write(string $path, array $parcels, Coverage $coverage): void
    {
        $pdo = new Sqlite('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // A build file that is not yet published is simply rebuilt if the import dies, so no journal and no fsync.
        $pdo->exec('PRAGMA journal_mode = OFF');
        $pdo->exec('PRAGMA synchronous = OFF');
        $this->createSchema($pdo);
        $this->insertAreas($pdo, $coverage);

        $insert = $pdo->prepare(
            'INSERT INTO parcels (id, cadastral_area_code, kind, base_number, subdivision, west, south, east, north) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $pdo->beginTransaction();
        foreach ($parcels as $converted) {
            $this->insertFile($converted->path, $coverage, $insert);
        }
        $pdo->commit();

        $pdo->exec('CREATE INDEX parcels_by_number ON parcels (base_number, subdivision, kind)');
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec(
            'CREATE TABLE cadastral_areas (
                code INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                municipality_code INTEGER NOT NULL,
                municipality_name TEXT NOT NULL,
                search_words TEXT NOT NULL
            )',
        );
        $pdo->exec(
            'CREATE TABLE parcels (
                id INTEGER PRIMARY KEY,
                cadastral_area_code INTEGER NOT NULL REFERENCES cadastral_areas (code),
                kind INTEGER NOT NULL,
                base_number INTEGER NOT NULL,
                subdivision INTEGER,
                west REAL NOT NULL,
                south REAL NOT NULL,
                east REAL NOT NULL,
                north REAL NOT NULL
            )',
        );
    }

    private function insertAreas(PDO $pdo, Coverage $coverage): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO cadastral_areas (code, name, municipality_code, municipality_name, search_words) VALUES (?, ?, ?, ?, ?)',
        );
        foreach ($coverage->areas() as $area) {
            $insert->execute([
                $area->code->value,
                $area->name,
                $area->municipality->code->value,
                $area->municipality->name,
                SearchText::of($area->name)->key() . ' ' . SearchText::of($area->municipality->name)->key(),
            ]);
        }
    }

    private function insertFile(string $path, Coverage $coverage, PDOStatement $insert): void
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw SearchIndexBuildFailed::because('cannot read ' . $path . '.');
        }

        try {
            for ($line = 1; ($text = fgets($handle)) !== false; ++$line) {
                if (trim($text) === '') {
                    continue;
                }
                $this->insertLine($text, $coverage, $insert, $path, $line);
            }
        } finally {
            fclose($handle);
        }
    }

    private function insertLine(string $text, Coverage $coverage, PDOStatement $insert, string $path, int $line): void
    {
        try {
            [$id, $area, $number, $boundary] = $this->parcel($text, $coverage);
            $bounds = $boundary->bounds();
            $insert->execute([
                $id->value,
                $area->code->value,
                $number->kind->value,
                $number->baseNumber,
                $number->subdivision,
                $bounds->southWest->longitude,
                $bounds->southWest->latitude,
                $bounds->northEast->longitude,
                $bounds->northEast->latitude,
            ]);
        } catch (InvalidArgumentException $exception) {
            throw SearchIndexBuildFailed::invalidParcel($path, $line, $exception->getMessage());
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::SQLITE_CONSTRAINT) {
                throw SearchIndexBuildFailed::invalidParcel($path, $line, 'duplicate parcel id: ' . $exception->getMessage());
            }

            throw $exception;
        }
    }

    /**
     * @return array{ParcelId, CadastralArea, ParcelNumber, ParcelBoundary}
     * @throws InvalidArgumentException
     */
    private function parcel(string $text, Coverage $coverage): array
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

        return [
            new ParcelId(self::integer($properties, 'id')),
            $coverage->area(new CadastralAreaCode(self::integer($properties, 'areaCode'))),
            new ParcelNumber(
                ParcelKind::fromCode(self::integer($properties, 'kind')),
                self::integer($properties, 'baseNumber'),
                array_key_exists('subdivision', $properties) ? self::integer($properties, 'subdivision') : null,
            ),
            ParcelBoundary::fromRings($this->rings($feature['geometry'] ?? null)),
        ];
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
}
