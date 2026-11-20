<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TilesetPublisher;
use ParcelMap\Application\Import\TilesetPublishFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;
use PDO;
use PDOException;

/**
 * Stamps the built MBTiles file with the version and export date, then renames it onto the target.
 * The rename is atomic on one filesystem, so a running server keeps reading the old file until it is replaced.
 */
final readonly class TilesetPublisherUsingMbtiles implements TilesetPublisher
{
    public function __construct(private string $targetFile) {}

    public function publish(BuiltTileset $tileset, TilesetVersion $version, ExportDate $exportDate): void
    {
        try {
            $this->stamp($tileset->path, $version, $exportDate);
        } catch (PDOException $exception) {
            throw TilesetPublishFailed::because(sprintf('cannot write the metadata of %s: %s', $tileset->path, $exception->getMessage()));
        }

        $directory = dirname($this->targetFile);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw TilesetPublishFailed::because('cannot create ' . $directory . '.');
        }
        if (!@rename($tileset->path, $this->targetFile)) {
            throw TilesetPublishFailed::because(sprintf('cannot move %s to %s.', $tileset->path, $this->targetFile));
        }
    }

    private function stamp(string $path, TilesetVersion $version, ExportDate $exportDate): void
    {
        $pdo = new PDO('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $statement = $pdo->prepare('INSERT OR REPLACE INTO metadata (name, value) VALUES (?, ?)');
        $statement->execute(['parcel_map_version', $version->value]);
        $statement->execute(['parcel_map_export_date', $exportDate->toString()]);
        $statement = null;
        $pdo = null;
    }
}
