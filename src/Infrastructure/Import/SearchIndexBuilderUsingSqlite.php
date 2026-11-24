<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use InvalidArgumentException;
use JsonException;
use ParcelMap\Application\Import\BuiltSearchIndex;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\ConvertedPlaces;
use ParcelMap\Application\Import\IndexedPlaceCounts;
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
use ParcelMap\Domain\Search\Place;
use ParcelMap\Domain\Search\PlaceKind;
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

    public function build(array $parcels, array $places, Coverage $coverage): BuiltSearchIndex
    {
        if (!is_dir($this->workDir) && !@mkdir($this->workDir, 0o777, true) && !is_dir($this->workDir)) {
            throw SearchIndexBuildFailed::because('cannot create ' . $this->workDir . '.');
        }
        $path = $this->workDir . '/search.sqlite';
        if (is_file($path) && !@unlink($path)) {
            throw SearchIndexBuildFailed::because('cannot delete ' . $path . '.');
        }

        try {
            $counts = $this->write($path, $parcels, $places, $coverage);
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception instanceof SearchIndexBuildFailed
                ? $exception
                : SearchIndexBuildFailed::because(sprintf('cannot write %s: %s', $path, $exception->getMessage()));
        }

        return new BuiltSearchIndex($path, $counts);
    }

    /**
     * @param non-empty-list<ConvertedParcels> $parcels
     * @param non-empty-list<ConvertedPlaces> $places
     */
    private function write(string $path, array $parcels, array $places, Coverage $coverage): IndexedPlaceCounts
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

        return $this->writePlaces($pdo, $places, $coverage);
    }

    /**
     * @param non-empty-list<ConvertedPlaces> $converted
     */
    private function writePlaces(PDO $pdo, array $converted, Coverage $coverage): IndexedPlaceCounts
    {
        $pdo->exec(
            'CREATE TABLE places (
                id INTEGER PRIMARY KEY,
                kind INTEGER NOT NULL,
                code INTEGER NOT NULL,
                municipality_code INTEGER NOT NULL,
                name TEXT NOT NULL,
                context TEXT NOT NULL,
                search_key TEXT NOT NULL,
                center_longitude REAL NOT NULL,
                center_latitude REAL NOT NULL,
                west REAL, south REAL, east REAL, north REAL,
                parcel_id INTEGER,
                UNIQUE (kind, code)
            )',
        );
        $pdo->exec("CREATE VIRTUAL TABLE places_fts USING fts5(text, content='', detail=none, prefix='1 2 3', tokenize='unicode61')");

        $areaBounds = $this->areaBounds($pdo);
        $reader = new PlaceFileReader();
        $all = [];
        foreach ($converted as $file) {
            array_push($all, ...$reader->read($pdo, $file, $coverage, $areaBounds));
        }

        $insert = $pdo->prepare(
            'INSERT INTO places (kind, code, municipality_code, name, context, search_key, center_longitude, center_latitude,'
            . ' west, south, east, north, parcel_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insertText = $pdo->prepare('INSERT INTO places_fts (rowid, text) VALUES (?, ?)');
        $counts = array_fill_keys(array_map(static fn(PlaceKind $kind): int => $kind->value, PlaceKind::cases()), 0);
        $pdo->beginTransaction();
        foreach ($all as $place) {
            $this->insertPlace($pdo, $insert, $insertText, $place);
            ++$counts[$place->kind->value];
        }
        $pdo->commit();
        $pdo->exec("INSERT INTO places_fts (places_fts) VALUES ('optimize')");

        return new IndexedPlaceCounts(
            $counts[PlaceKind::Municipality->value],
            $counts[PlaceKind::CadastralArea->value],
            $counts[PlaceKind::MunicipalityPart->value],
            $counts[PlaceKind::Street->value],
            $counts[PlaceKind::Address->value],
        );
    }

    private function insertPlace(PDO $pdo, PDOStatement $insert, PDOStatement $insertText, Place $place): void
    {
        $bounds = $place->bounds;
        $insert->execute([
            $place->kind->value,
            $place->code,
            $place->municipality->value,
            $place->name,
            $place->context,
            $place->key(),
            $place->center->longitude,
            $place->center->latitude,
            $bounds?->southWest->longitude,
            $bounds?->southWest->latitude,
            $bounds?->northEast->longitude,
            $bounds?->northEast->latitude,
            $place->parcel?->value,
        ]);
        $insertText->execute([(int) $pdo->lastInsertId(), SearchText::of($place->name . ' ' . $place->context)->key()]);
    }

    /**
     * @return array<int, array{float, float, float, float}>
     */
    private function areaBounds(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT cadastral_area_code, MIN(west), MIN(south), MAX(east), MAX(north) FROM parcels GROUP BY cadastral_area_code',
        );
        $bounds = [];
        /** @var list<list<int|float|string|null>> $fetched */
        $fetched = $rows === false ? [] : $rows->fetchAll(PDO::FETCH_NUM);
        foreach ($fetched as $row) {
            $bounds[(int) $row[0]] = [(float) $row[1], (float) $row[2], (float) $row[3], (float) $row[4]];
        }

        return $bounds;
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
