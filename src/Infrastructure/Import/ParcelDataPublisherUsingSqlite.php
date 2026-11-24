<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\BuiltParcelStore;
use ParcelMap\Application\Import\BuiltSearchIndex;
use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\ParcelDataPublisher;
use ParcelMap\Application\Import\ParcelDataPublishFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;
use PDO;
use PDOException;
use Pdo\Sqlite;

/**
 * Publishes the tileset, the parcel store and the search index of one import under one version.
 *
 * The tileset rename is the single commit point. The store and the index are placed first at
 * `{directory}/{version}.sqlite`, paths nobody reads until a tileset carries that version; then the tileset is renamed
 * onto the live path (atomic on one filesystem, so a running server keeps reading the old file until it is replaced).
 * If that rename fails, both placed files are removed again. So a reader never sees a tileset without its store and
 * index. Afterwards every store and index except the new ones and the ones of the previously live tileset is deleted:
 * requests in flight may still be reading the previous ones.
 * Version strings are safe as file names, because TilesetVersion allows only letters, digits and hyphens.
 */
final readonly class ParcelDataPublisherUsingSqlite implements ParcelDataPublisher
{
    public function __construct(
        private string $tilesetFile,
        private string $parcelStoreDirectory,
        private string $searchIndexDirectory,
    ) {}

    public function publish(BuiltTileset $tileset, BuiltParcelStore $store, BuiltSearchIndex $index, TilesetVersion $version, ExportDate $exportDate): void
    {
        $this->stamp($tileset->path, $version, $exportDate);
        $this->stamp($store->path, $version, $exportDate);
        $this->stamp($index->path, $version, $exportDate);
        $liveVersion = $this->liveVersion();

        $storeTarget = sprintf('%s/%s.sqlite', $this->parcelStoreDirectory, $version->value);
        self::createDirectory($this->parcelStoreDirectory);
        if (!@rename($store->path, $storeTarget)) {
            throw ParcelDataPublishFailed::because(sprintf('cannot move %s to %s.', $store->path, $storeTarget));
        }

        $indexTarget = sprintf('%s/%s.sqlite', $this->searchIndexDirectory, $version->value);
        self::createDirectory($this->searchIndexDirectory);
        if (!@rename($index->path, $indexTarget)) {
            @unlink($storeTarget);

            throw ParcelDataPublishFailed::because(sprintf('cannot move %s to %s.', $index->path, $indexTarget));
        }

        self::createDirectory(dirname($this->tilesetFile));
        if (!@rename($tileset->path, $this->tilesetFile)) {
            @unlink($storeTarget);
            @unlink($indexTarget);

            throw ParcelDataPublishFailed::because(sprintf('cannot move %s to %s.', $tileset->path, $this->tilesetFile));
        }

        $keptVersions = array_filter([$version->value, $liveVersion]);
        $this->prune($this->parcelStoreDirectory, $keptVersions);
        $this->prune($this->searchIndexDirectory, $keptVersions);
    }

    private function stamp(string $path, TilesetVersion $version, ExportDate $exportDate): void
    {
        try {
            $pdo = new PDO('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $statement = $pdo->prepare('INSERT OR REPLACE INTO metadata (name, value) VALUES (?, ?)');
            $statement->execute(['parcel_map_version', $version->value]);
            $statement->execute(['parcel_map_export_date', $exportDate->toString()]);
        } catch (PDOException $exception) {
            throw ParcelDataPublishFailed::because(sprintf('cannot write the metadata of %s: %s', $path, $exception->getMessage()));
        }
    }

    /**
     * @return string|null the version of the tileset that is live now; null when there is none or it is unreadable
     */
    private function liveVersion(): ?string
    {
        if (!is_file($this->tilesetFile)) {
            return null;
        }

        try {
            $pdo = new Sqlite('sqlite:' . $this->tilesetFile, options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY,
            ]);
            $statement = $pdo->prepare("SELECT value FROM metadata WHERE name = 'parcel_map_version'");
            $statement->execute();
            $version = $statement->fetchColumn();
        } catch (PDOException) {
            return null;
        }

        return is_string($version) ? $version : null;
    }

    private static function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw ParcelDataPublishFailed::because('cannot create ' . $directory . '.');
        }
    }

    /**
     * @param array<array-key, string> $keptVersions
     */
    private function prune(string $directory, array $keptVersions): void
    {
        $kept = array_map(static fn(string $version): string => sprintf('%s/%s.sqlite', $directory, $version), $keptVersions);
        foreach (glob($directory . '/*.sqlite') ?: [] as $file) {
            // The new data is already live and a leftover file is harmless, so a failed delete is ignored.
            if (!in_array($file, $kept, true)) {
                @unlink($file);
            }
        }
    }
}
