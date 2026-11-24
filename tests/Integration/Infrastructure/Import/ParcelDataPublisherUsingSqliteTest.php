<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\BuiltParcelStore;
use ParcelMap\Application\Import\BuiltSearchIndex;
use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\ParcelDataPublishFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Infrastructure\Import\ParcelDataPublisherUsingSqlite;
use ParcelMap\Tests\Support\MbtilesMetadata;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelDataPublisherUsingSqliteTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    #[Test]
    public function it_stamps_all_three_files_with_the_version_and_export_date_and_moves_them_to_their_targets(): void
    {
        $tileset = $this->builtTileset('built.mbtiles', 'first');
        $store = $this->builtStore('built.sqlite');
        $index = $this->builtIndex('built-index.sqlite');

        $this->publisher()->publish($tileset, $store, $index, new TilesetVersion('20261120093000'), ExportDate::fromString('2026-10-31'));

        self::assertFileDoesNotExist($tileset->path);
        self::assertFileDoesNotExist($store->path);
        self::assertFileDoesNotExist($index->path);
        $metadata = MbtilesMetadata::read($this->tilesetFile());
        self::assertSame('20261120093000', $metadata['parcel_map_version']);
        self::assertSame('2026-10-31', $metadata['parcel_map_export_date']);
        self::assertSame('first', $metadata['name']);
        $storeMetadata = MbtilesMetadata::read($this->storeDirectory() . '/20261120093000.sqlite');
        self::assertSame('20261120093000', $storeMetadata['parcel_map_version']);
        self::assertSame('2026-10-31', $storeMetadata['parcel_map_export_date']);
        $indexMetadata = MbtilesMetadata::read($this->indexDirectory() . '/20261120093000.sqlite');
        self::assertSame('20261120093000', $indexMetadata['parcel_map_version']);
        self::assertSame('2026-10-31', $indexMetadata['parcel_map_export_date']);
    }

    #[Test]
    public function it_replaces_an_already_published_tileset(): void
    {
        $publisher = $this->publisher();
        $publisher->publish($this->builtTileset('one.mbtiles', 'one'), $this->builtStore('one.sqlite'), $this->builtIndex('index-one.sqlite'), new TilesetVersion('v1'), ExportDate::fromString('2026-09-30'));

        $publisher->publish($this->builtTileset('two.mbtiles', 'two'), $this->builtStore('two.sqlite'), $this->builtIndex('index-two.sqlite'), new TilesetVersion('v2'), ExportDate::fromString('2026-10-31'));

        $metadata = MbtilesMetadata::read($this->tilesetFile());
        self::assertSame('two', $metadata['name']);
        self::assertSame('v2', $metadata['parcel_map_version']);
    }

    #[Test]
    public function it_keeps_the_store_of_the_previous_live_tileset_and_deletes_all_others(): void
    {
        $this->publisher()->publish($this->builtTileset('a.mbtiles', 'a'), $this->builtStore('a.sqlite'), $this->builtIndex('index-a.sqlite'), new TilesetVersion('A'), ExportDate::fromString('2026-08-31'));
        touch($this->storeDirectory() . '/B.sqlite');
        touch($this->storeDirectory() . '/C.sqlite');
        touch($this->storeDirectory() . '/notes.txt');

        $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('D'), ExportDate::fromString('2026-10-31'));

        self::assertSame(['A.sqlite', 'D.sqlite', 'notes.txt'], $this->storeFiles());
    }

    #[Test]
    public function it_keeps_the_index_of_the_previous_live_tileset_and_deletes_all_others(): void
    {
        $this->publisher()->publish($this->builtTileset('a.mbtiles', 'a'), $this->builtStore('a.sqlite'), $this->builtIndex('index-a.sqlite'), new TilesetVersion('A'), ExportDate::fromString('2026-08-31'));
        touch($this->indexDirectory() . '/B.sqlite');
        touch($this->indexDirectory() . '/C.sqlite');

        $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('D'), ExportDate::fromString('2026-10-31'));

        self::assertSame(['A.sqlite', 'D.sqlite'], $this->indexFiles());
    }

    #[Test]
    public function it_removes_the_placed_store_when_the_index_cannot_be_moved(): void
    {
        mkdir($this->indexDirectory() . '/D.sqlite', 0o777, true);
        touch($this->indexDirectory() . '/D.sqlite/blocker');

        try {
            $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('D'), ExportDate::fromString('2026-10-31'));
            self::fail('Expected ParcelDataPublishFailed.');
        } catch (ParcelDataPublishFailed) {
            self::assertSame([], $this->storeFiles());
            self::assertFileDoesNotExist($this->tilesetFile());
        }
    }

    #[Test]
    public function it_reports_an_index_that_is_not_an_sqlite_file(): void
    {
        $path = $this->directory->path . '/broken-index.sqlite';
        file_put_contents($path, 'this is not sqlite, but long enough to be looked at as a database header');

        try {
            $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), new BuiltSearchIndex($path), new TilesetVersion('v1'), ExportDate::fromString('2026-10-31'));
            self::fail('Expected ParcelDataPublishFailed.');
        } catch (ParcelDataPublishFailed) {
            self::assertFileDoesNotExist($this->tilesetFile());
            self::assertSame([], $this->storeFiles());
        }
    }

    #[Test]
    public function it_keeps_only_the_new_store_when_there_is_no_live_tileset(): void
    {
        mkdir($this->storeDirectory(), 0o777, true);
        touch($this->storeDirectory() . '/old.sqlite');

        $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('D'), ExportDate::fromString('2026-10-31'));

        self::assertSame(['D.sqlite'], $this->storeFiles());
    }

    #[Test]
    public function it_treats_an_unreadable_live_tileset_as_having_no_live_version(): void
    {
        mkdir($this->directory->path . '/tiles');
        file_put_contents($this->tilesetFile(), 'this is not sqlite, but long enough to be looked at as a database header');
        mkdir($this->storeDirectory(), 0o777, true);
        touch($this->storeDirectory() . '/old.sqlite');

        $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('D'), ExportDate::fromString('2026-10-31'));

        self::assertSame(['D.sqlite'], $this->storeFiles());
        self::assertSame('D', MbtilesMetadata::read($this->tilesetFile())['parcel_map_version']);
    }

    #[Test]
    public function it_removes_the_new_store_and_index_when_the_tileset_cannot_be_moved_to_its_target(): void
    {
        mkdir($this->tilesetFile(), 0o777, true);

        try {
            $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('D'), ExportDate::fromString('2026-10-31'));
            self::fail('Expected ParcelDataPublishFailed.');
        } catch (ParcelDataPublishFailed) {
            self::assertSame([], $this->storeFiles());
            self::assertSame([], $this->indexFiles());
        }
    }

    #[Test]
    public function it_reports_a_tileset_that_is_not_an_sqlite_file(): void
    {
        $path = $this->directory->path . '/broken.mbtiles';
        file_put_contents($path, 'this is not sqlite, but long enough to be looked at as a database header');

        try {
            $this->publisher()->publish(new BuiltTileset($path), $this->builtStore('d.sqlite'), $this->builtIndex('index-d.sqlite'), new TilesetVersion('v1'), ExportDate::fromString('2026-10-31'));
            self::fail('Expected ParcelDataPublishFailed.');
        } catch (ParcelDataPublishFailed) {
            self::assertFileDoesNotExist($this->tilesetFile());
            self::assertSame([], $this->storeFiles());
        }
    }

    #[Test]
    public function it_reports_a_store_that_is_not_an_sqlite_file(): void
    {
        $path = $this->directory->path . '/broken.sqlite';
        file_put_contents($path, 'this is not sqlite, but long enough to be looked at as a database header');

        try {
            $this->publisher()->publish($this->builtTileset('d.mbtiles', 'd'), new BuiltParcelStore($path, []), $this->builtIndex('index-d.sqlite'), new TilesetVersion('v1'), ExportDate::fromString('2026-10-31'));
            self::fail('Expected ParcelDataPublishFailed.');
        } catch (ParcelDataPublishFailed) {
            self::assertFileDoesNotExist($this->tilesetFile());
        }
    }

    private function publisher(): ParcelDataPublisherUsingSqlite
    {
        return new ParcelDataPublisherUsingSqlite($this->tilesetFile(), $this->storeDirectory(), $this->indexDirectory());
    }

    private function indexDirectory(): string
    {
        return $this->directory->path . '/search';
    }

    /**
     * @return list<string>
     */
    private function indexFiles(): array
    {
        if (!is_dir($this->indexDirectory())) {
            return [];
        }
        $files = array_values(array_diff(scandir($this->indexDirectory()) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }

    private function tilesetFile(): string
    {
        return $this->directory->path . '/tiles/parcels.mbtiles';
    }

    private function storeDirectory(): string
    {
        return $this->directory->path . '/parcels';
    }

    /**
     * @return list<string>
     */
    private function storeFiles(): array
    {
        if (!is_dir($this->storeDirectory())) {
            return [];
        }
        $files = array_values(array_diff(scandir($this->storeDirectory()) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }

    private function builtTileset(string $name, string $tilesetName): BuiltTileset
    {
        $path = $this->directory->path . '/' . $name;
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE metadata (name TEXT, value TEXT, UNIQUE (name))');
        $pdo->exec("INSERT INTO metadata (name, value) VALUES ('name', '" . $tilesetName . "')");

        return new BuiltTileset($path);
    }

    private function builtStore(string $name): BuiltParcelStore
    {
        $path = $this->directory->path . '/' . $name;
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL)');

        return new BuiltParcelStore($path, []);
    }

    private function builtIndex(string $name): BuiltSearchIndex
    {
        $path = $this->directory->path . '/' . $name;
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL)');

        return new BuiltSearchIndex($path);
    }
}
