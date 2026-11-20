<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Map;

use InvalidArgumentException;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\ParcelTilesetRepository;
use ParcelMap\Domain\Map\ParcelTilesetUnreadable;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Map\VectorTile;
use PDOException;
use PDO;
use Pdo\Sqlite;

/**
 * Reads the published MBTiles file. A connection is opened per call: it costs about 2 ms, and a re-import
 * replaces the file by rename, so a long-lived connection would keep serving the old one.
 */
final readonly class ParcelTilesetRepositoryUsingMbtiles implements ParcelTilesetRepository
{
    public function __construct(private string $file) {}

    public function current(): ParcelTileset
    {
        $metadata = $this->metadata();

        try {
            return new ParcelTileset(
                new TilesetVersion($this->required($metadata, 'parcel_map_version')),
                ExportDate::fromString($this->required($metadata, 'parcel_map_export_date')),
                $this->bounds($this->required($metadata, 'bounds')),
                $this->integer($this->required($metadata, 'minzoom'), 'minzoom'),
                $this->integer($this->required($metadata, 'maxzoom'), 'maxzoom'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ParcelTilesetUnreadable::because($exception->getMessage());
        }
    }

    public function tile(TileCoordinate $coordinate): VectorTile
    {
        $connection = $this->connect();

        try {
            $statement = $connection->prepare(
                'SELECT tile_data FROM tiles WHERE zoom_level = ? AND tile_column = ? AND tile_row = ?',
            );
            // MBTiles counts rows from the bottom (TMS), web maps from the top.
            $statement->execute([$coordinate->zoom, $coordinate->x, (1 << $coordinate->zoom) - 1 - $coordinate->y]);
            $data = $statement->fetchColumn();
        } catch (PDOException $exception) {
            throw ParcelTilesetUnreadable::because($exception->getMessage());
        }

        return is_string($data) ? VectorTile::fromGzippedData($data) : VectorTile::empty();
    }

    private function connect(): Sqlite
    {
        if (!is_file($this->file)) {
            throw ParcelDataNotImported::create();
        }

        try {
            return new Sqlite(
                'sqlite:' . $this->file,
                options: [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $exception) {
            throw ParcelTilesetUnreadable::because($exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function metadata(): array
    {
        $connection = $this->connect();

        try {
            $statement = $connection->query('SELECT name, value FROM metadata');
            /** @var array<string, string> $metadata */
            $metadata = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $exception) {
            throw ParcelTilesetUnreadable::because($exception->getMessage());
        }

        return $metadata;
    }

    /**
     * @param array<string, string> $metadata
     */
    private function required(array $metadata, string $name): string
    {
        return $metadata[$name] ?? throw ParcelTilesetUnreadable::because(sprintf('the metadata "%s" is missing.', $name));
    }

    private function integer(string $value, string $name): int
    {
        if (!ctype_digit($value)) {
            throw ParcelTilesetUnreadable::because(sprintf('the metadata "%s" is not a number: "%s".', $name, $value));
        }

        return (int) $value;
    }

    private function bounds(string $value): Bounds
    {
        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 4 || array_filter($parts, is_numeric(...)) !== $parts) {
            throw ParcelTilesetUnreadable::because(sprintf('the metadata "bounds" is not "west,south,east,north": "%s".', $value));
        }
        [$west, $south, $east, $north] = array_map(floatval(...), $parts);

        return new Bounds(new Coordinates($west, $south), new Coordinates($east, $north));
    }
}
