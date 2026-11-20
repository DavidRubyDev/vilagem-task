<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Map;

use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTilesetUnreadable;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Infrastructure\Map\ParcelTilesetRepositoryUsingMbtiles;
use ParcelMap\Tests\Support\FixtureTileset;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelTilesetRepositoryUsingMbtilesTest extends TestCase
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
    public function it_describes_the_fixture_tileset(): void
    {
        $tileset = new ParcelTilesetRepositoryUsingMbtiles(FixtureTileset::file())->current();

        self::assertSame(FixtureTileset::VERSION, $tileset->version->value);
        self::assertSame(FixtureTileset::EXPORT_DATE, $tileset->exportDate->toString());
        self::assertSame(FixtureTileset::WEST, $tileset->bounds->southWest->longitude);
        self::assertSame(FixtureTileset::SOUTH, $tileset->bounds->southWest->latitude);
        self::assertSame(FixtureTileset::EAST, $tileset->bounds->northEast->longitude);
        self::assertSame(FixtureTileset::NORTH, $tileset->bounds->northEast->latitude);
        self::assertSame(9, $tileset->minZoom);
        self::assertSame(16, $tileset->maxZoom);
    }

    #[Test]
    public function it_reads_a_tile_by_its_xyz_coordinate(): void
    {
        $tile = new ParcelTilesetRepositoryUsingMbtiles(FixtureTileset::file())->tile(new TileCoordinate(12, 2222, 1381));

        self::assertFalse($tile->isEmpty());
        self::assertStringStartsWith("\x1f\x8b", $tile->gzippedData());
    }

    #[Test]
    public function it_returns_an_empty_tile_where_the_tileset_has_none(): void
    {
        $tile = new ParcelTilesetRepositoryUsingMbtiles(FixtureTileset::file())->tile(new TileCoordinate(14, 8891, 5525));

        self::assertTrue($tile->isEmpty());
    }

    #[Test]
    public function it_reports_missing_data_when_the_file_does_not_exist(): void
    {
        $repository = new ParcelTilesetRepositoryUsingMbtiles($this->directory->path . '/missing.mbtiles');

        try {
            $repository->current();
            self::fail('Expected ParcelDataNotImported.');
        } catch (ParcelDataNotImported) {
            $this->expectException(ParcelDataNotImported::class);
            $repository->tile(new TileCoordinate(12, 2222, 1381));
        }
    }

    #[Test]
    public function it_does_not_create_the_file_when_it_is_missing(): void
    {
        $file = $this->directory->path . '/missing.mbtiles';

        try {
            new ParcelTilesetRepositoryUsingMbtiles($file)->current();
        } catch (ParcelDataNotImported) {
        }

        self::assertFileDoesNotExist($file);
    }

    #[Test]
    public function it_reports_a_tileset_without_the_version_metadata_as_unreadable(): void
    {
        $file = $this->directory->path . '/foreign.mbtiles';
        $pdo = new PDO('sqlite:' . $file);
        $pdo->exec('CREATE TABLE metadata (name TEXT, value TEXT)');
        $pdo->exec("INSERT INTO metadata VALUES ('minzoom', '9')");
        $pdo = null;

        $this->expectException(ParcelTilesetUnreadable::class);

        new ParcelTilesetRepositoryUsingMbtiles($file)->current();
    }
}
