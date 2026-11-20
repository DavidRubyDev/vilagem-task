<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TilesetInspectionFailed;
use ParcelMap\Domain\Map\TilesetStatistics;
use ParcelMap\Domain\Map\ZoomTileStatistics;
use ParcelMap\Infrastructure\Import\TilesetInspectorUsingMbtiles;
use ParcelMap\Tests\Support\FixtureTileset;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TilesetInspectorUsingMbtilesTest extends TestCase
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
    public function it_reports_the_tile_count_and_the_largest_tile_of_every_zoom_level_of_the_fixture_tileset(): void
    {
        $statistics = $this->inspect(FixtureTileset::file());

        $figures = [];
        foreach ($statistics->zooms() as $zoom) {
            $figures[$zoom->zoom] = [$zoom->tileCount, $zoom->largestTileBytes, self::tile($zoom)];
        }
        self::assertSame(
            [
                9 => [1, 211, '9/277/172'],
                10 => [1, 436, '10/555/345'],
                11 => [1, 567, '11/1111/690'],
                12 => [2, 595, '12/2222/1381'],
                13 => [2, 679, '13/4445/2762'],
                14 => [3, 742, '14/8890/5525'],
                15 => [5, 862, '15/17781/11050'],
                16 => [7, 1258, '16/35562/22101'],
            ],
            $figures,
        );
        self::assertSame(22, $statistics->tileCount());
        self::assertSame(16, $statistics->largest()->zoom);
    }

    #[Test]
    public function it_does_not_modify_the_tileset(): void
    {
        $before = md5_file(FixtureTileset::file());

        $this->inspect(FixtureTileset::file());

        self::assertSame($before, md5_file(FixtureTileset::file()));
    }

    #[Test]
    public function it_reports_a_missing_file_and_does_not_create_it(): void
    {
        $path = $this->directory->path . '/missing.mbtiles';

        try {
            $this->inspect($path);
            self::fail('Expected TilesetInspectionFailed.');
        } catch (TilesetInspectionFailed) {
            self::assertFileDoesNotExist($path);
        }
    }

    #[Test]
    public function it_reports_a_file_that_is_not_a_sqlite_database(): void
    {
        $path = $this->directory->path . '/broken.mbtiles';
        file_put_contents($path, str_repeat('this is not a database', 100));

        $this->expectException(TilesetInspectionFailed::class);

        $this->inspect($path);
    }

    #[Test]
    public function it_reports_a_tileset_without_tiles(): void
    {
        $path = $this->directory->path . '/empty.mbtiles';
        $pdo = new PDO('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE tiles (zoom_level INTEGER, tile_column INTEGER, tile_row INTEGER, tile_data BLOB)');
        $pdo = null;

        $this->expectException(TilesetInspectionFailed::class);
        $this->expectExceptionMessage('contains no tiles');

        $this->inspect($path);
    }

    private function inspect(string $path): TilesetStatistics
    {
        return new TilesetInspectorUsingMbtiles()->inspect(new BuiltTileset($path));
    }

    private static function tile(ZoomTileStatistics $zoom): string
    {
        return sprintf('%d/%d/%d', $zoom->largestTile->zoom, $zoom->largestTile->x, $zoom->largestTile->y);
    }
}
