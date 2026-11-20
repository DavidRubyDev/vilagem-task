<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use InvalidArgumentException;
use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TilesetInspectionFailed;
use ParcelMap\Application\Import\TilesetInspector;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\TilesetStatistics;
use ParcelMap\Domain\Map\ZoomTileStatistics;
use PDO;
use PDOException;
use Pdo\Sqlite;

/** Reads the size of the tiles of a built MBTiles file, read-only (`tiles` is a view in tippecanoe output). */
final readonly class TilesetInspectorUsingMbtiles implements TilesetInspector
{
    public function inspect(BuiltTileset $tileset): TilesetStatistics
    {
        if (!is_file($tileset->path)) {
            throw TilesetInspectionFailed::because(sprintf('%s does not exist.', $tileset->path));
        }

        try {
            return $this->statistics($this->connect($tileset->path), $tileset->path);
        } catch (PDOException|InvalidArgumentException $exception) {
            throw TilesetInspectionFailed::because(sprintf('cannot read %s: %s', $tileset->path, $exception->getMessage()));
        }
    }

    private function connect(string $path): Sqlite
    {
        return new Sqlite(
            'sqlite:' . $path,
            options: [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function statistics(Sqlite $connection, string $path): TilesetStatistics
    {
        $perZoom = $connection->query(
            'SELECT zoom_level, COUNT(*), MAX(LENGTH(tile_data)) FROM tiles GROUP BY zoom_level ORDER BY zoom_level',
        );
        $largest = $connection->prepare(
            'SELECT tile_column, tile_row FROM tiles WHERE zoom_level = ? ORDER BY LENGTH(tile_data) DESC, tile_column, tile_row LIMIT 1',
        );

        $zooms = [];
        foreach ($perZoom === false ? [] : $perZoom->fetchAll(PDO::FETCH_NUM) as $row) {
            $zoom = self::integer($row, 0);
            $largest->execute([$zoom]);
            $tile = $largest->fetch(PDO::FETCH_NUM);
            if (!is_array($tile)) {
                throw TilesetInspectionFailed::because(sprintf('%s has no tile on the zoom level %d.', $path, $zoom));
            }
            // MBTiles counts rows from the bottom (TMS), web maps from the top.
            $y = (1 << $zoom) - 1 - self::integer($tile, 1);
            $zooms[] = new ZoomTileStatistics(
                $zoom,
                self::integer($row, 1),
                self::integer($row, 2),
                new TileCoordinate($zoom, self::integer($tile, 0), $y),
            );
        }
        if ($zooms === []) {
            throw TilesetInspectionFailed::because(sprintf('%s contains no tiles.', $path));
        }

        return new TilesetStatistics(...$zooms);
    }

    /**
     * @param mixed $row a database row; PDO declares its rows and values as mixed, so it is checked here
     */
    private static function integer(mixed $row, int $column): int
    {
        $value = is_array($row) ? ($row[$column] ?? null) : null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw TilesetInspectionFailed::because(sprintf('column %d of a tile statistics row is not a number.', $column));
        }

        return (int) $value;
    }
}
