<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TilesetPublishFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Infrastructure\Import\TilesetPublisherUsingMbtiles;
use ParcelMap\Tests\Support\TemporaryDirectory;
use ParcelMap\Tests\Support\MbtilesMetadata;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TilesetPublisherUsingMbtilesTest extends TestCase
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
    public function it_writes_the_version_and_export_date_and_moves_the_file_to_the_target(): void
    {
        $built = $this->builtTileset('built.mbtiles', 'first');
        $target = $this->directory->path . '/tiles/parcels.mbtiles';

        new TilesetPublisherUsingMbtiles($target)->publish(
            $built,
            new TilesetVersion('20261120093000'),
            ExportDate::fromString('2026-10-31'),
        );

        self::assertFileDoesNotExist($built->path);
        $metadata = MbtilesMetadata::read($target);
        self::assertSame('20261120093000', $metadata['parcel_map_version']);
        self::assertSame('2026-10-31', $metadata['parcel_map_export_date']);
        self::assertSame('first', $metadata['name']);
    }

    #[Test]
    public function it_replaces_an_already_published_tileset(): void
    {
        $target = $this->directory->path . '/tiles/parcels.mbtiles';
        $publisher = new TilesetPublisherUsingMbtiles($target);
        $publisher->publish($this->builtTileset('one.mbtiles', 'one'), new TilesetVersion('v1'), ExportDate::fromString('2026-09-30'));

        $publisher->publish($this->builtTileset('two.mbtiles', 'two'), new TilesetVersion('v2'), ExportDate::fromString('2026-10-31'));

        $metadata = MbtilesMetadata::read($target);
        self::assertSame('two', $metadata['name']);
        self::assertSame('v2', $metadata['parcel_map_version']);
    }

    #[Test]
    public function it_reports_an_input_that_is_not_an_sqlite_file(): void
    {
        $path = $this->directory->path . '/broken.mbtiles';
        file_put_contents($path, 'this is not sqlite, but long enough to be looked at as a database header');
        $target = $this->directory->path . '/tiles/parcels.mbtiles';

        try {
            new TilesetPublisherUsingMbtiles($target)->publish(
                new BuiltTileset($path),
                new TilesetVersion('v1'),
                ExportDate::fromString('2026-10-31'),
            );
            self::fail('Expected TilesetPublishFailed.');
        } catch (TilesetPublishFailed) {
            self::assertFileDoesNotExist($target);
        }
    }

    private function builtTileset(string $name, string $tilesetName): BuiltTileset
    {
        $path = $this->directory->path . '/' . $name;
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE metadata (name TEXT, value TEXT, UNIQUE (name))');
        $pdo->exec("INSERT INTO metadata (name, value) VALUES ('name', '" . $tilesetName . "')");

        return new BuiltTileset($path);
    }

}
