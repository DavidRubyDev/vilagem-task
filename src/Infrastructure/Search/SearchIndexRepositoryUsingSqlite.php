<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Search;

use InvalidArgumentException;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Search\ParcelMatch;
use ParcelMap\Domain\Search\SearchIndexNotPublished;
use ParcelMap\Domain\Search\SearchIndexRepository;
use ParcelMap\Domain\Search\SearchIndexUnreadable;
use ParcelMap\Domain\Search\SearchQuery;
use PDO;
use PDOException;
use Pdo\Sqlite;

/**
 * Reads the search index of a tileset version. The file is opened read-only per call (as the parcel store is),
 * because a re-import replaces files and a long-lived connection would keep serving the old one.
 */
final readonly class SearchIndexRepositoryUsingSqlite implements SearchIndexRepository
{
    public function __construct(private string $directory) {}

    public function isPublished(TilesetVersion $version): bool
    {
        return is_file($this->file($version));
    }

    public function findParcels(TilesetVersion $version, SearchQuery $query, ?Coordinates $near, int $limit): array
    {
        $number = $query->parcelNumber();
        if ($number === null) {
            return [];
        }
        $file = $this->file($version);
        if (!is_file($file)) {
            throw SearchIndexNotPublished::forVersion($version->value);
        }

        try {
            $rows = $this->parcelRows($file, $query, $number->baseNumber, $number->subdivision, $number->kind === ParcelKind::Building, $near, $limit);

            return array_map($this->parcelMatch(...), $rows);
        } catch (PDOException|InvalidArgumentException $exception) {
            throw SearchIndexUnreadable::because($exception->getMessage());
        }
    }

    private function file(TilesetVersion $version): string
    {
        return sprintf('%s/%s.sqlite', $this->directory, $version->value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parcelRows(string $file, SearchQuery $query, int $base, ?int $subdivision, bool $buildingsOnly, ?Coordinates $near, int $limit): array
    {
        $where = ['p.base_number = :base', 'p.subdivision IS :subdivision'];
        $parameters = [':base' => $base, ':subdivision' => $subdivision, ':limit' => $limit];
        if ($buildingsOnly) {
            $where[] = 'p.kind = 1';
        }
        if ($query->cadastralAreaCode() !== null) {
            $where[] = 'p.cadastral_area_code = :area';
            $parameters[':area'] = $query->cadastralAreaCode()->value;
        }
        // The terms hold [a-z0-9] only (SearchQuery), so they contain no LIKE wildcard.
        foreach ($query->nameTerms() as $index => $term) {
            $where[] = sprintf("(' ' || a.search_words) LIKE :term%d", $index);
            $parameters[':term' . $index] = '% ' . $term . '%';
        }

        $order = 'p.kind DESC, a.name, p.id';
        if ($near !== null) {
            // Squared distance between the centres, the longitude scaled to the latitude of the map centre.
            $order = '((p.west + p.east) / 2 - :lon) * ((p.west + p.east) / 2 - :lon) * :k'
                . ' + ((p.south + p.north) / 2 - :lat) * ((p.south + p.north) / 2 - :lat), ' . $order;
            $parameters[':lon'] = $near->longitude;
            $parameters[':lat'] = $near->latitude;
            $parameters[':k'] = cos(deg2rad($near->latitude)) ** 2;
        }

        $statement = $this->connect($file)->prepare(
            'SELECT p.id, p.kind, p.base_number, p.subdivision, a.code AS area_code, a.name AS area_name,'
            . ' a.municipality_code, a.municipality_name'
            . ' FROM parcels p JOIN cadastral_areas a ON a.code = p.cadastral_area_code'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order
            . ' LIMIT :limit',
        );
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    private function connect(string $file): Sqlite
    {
        return new Sqlite(
            'sqlite:' . $file,
            options: [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function parcelMatch(array $row): ParcelMatch
    {
        return new ParcelMatch(
            new ParcelId(self::integer($row, 'id')),
            new ParcelNumber(
                ParcelKind::fromCode(self::integer($row, 'kind')),
                self::integer($row, 'base_number'),
                ($row['subdivision'] ?? null) === null ? null : self::integer($row, 'subdivision'),
            ),
            new CadastralArea(
                new CadastralAreaCode(self::integer($row, 'area_code')),
                self::string($row, 'area_name'),
                new Municipality(new MunicipalityCode(self::integer($row, 'municipality_code')), self::string($row, 'municipality_name')),
            ),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        return is_int($value) ? $value : throw new InvalidArgumentException(sprintf('the column "%s" is not an integer.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return is_string($value) ? $value : throw new InvalidArgumentException(sprintf('the column "%s" is not text.', $column));
    }
}
