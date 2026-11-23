<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\TileBuildFailed;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Infrastructure\Import\TilesetBuilderUsingTippecanoe;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Tests\Support\TemporaryDirectory;
use ParcelMap\Tests\Support\MbtilesMetadata;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TilesetBuilderUsingTippecanoeTest extends TestCase
{
    private TemporaryDirectory $work;

    protected function setUp(): void
    {
        $this->work = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->work->remove();
    }

    #[Test]
    public function it_builds_an_mbtiles_file_with_the_parcels_layer(): void
    {
        $built = $this->builder()->build([$this->fixtureParcels()], TileSizeBudget::smoothMap());

        self::assertSame($this->work->path . '/parcels.mbtiles', $built->path);
        $metadata = MbtilesMetadata::read($built->path);
        self::assertSame('9', $metadata['minzoom']);
        self::assertSame('16', $metadata['maxzoom']);
        self::assertSame('pbf', $metadata['format']);
        $json = json_decode($metadata['json'], true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertIsArray($json['vector_layers']);
        self::assertIsArray($json['vector_layers'][0]);
        self::assertSame('parcels', $json['vector_layers'][0]['id']);
    }

    #[Test]
    public function it_keeps_the_parcel_id_and_land_type_in_the_tiles(): void
    {
        $built = $this->builder()->build([$this->fixtureParcels()], TileSizeBudget::smoothMap());

        $decoded = $this->runTool(['tippecanoe-decode', $built->path, '14', '8890', '5525']);

        self::assertStringContainsString('"id": 1753295604', $decoded);
        self::assertStringContainsString('landType', $decoded);
        self::assertStringNotContainsString('areaCode', $decoded);
    }

    #[Test]
    public function it_keeps_only_the_land_type_of_the_parcel_attributes_in_the_tiles(): void
    {
        $built = $this->builder()->build([$this->fixtureParcels()], TileSizeBudget::smoothMap());

        $tile = json_decode($this->runTool(['tippecanoe-decode', $built->path, '14', '8890', '5525']), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($tile);
        $features = self::featuresOf($tile);
        self::assertNotSame([], $features);
        foreach ($features as $feature) {
            self::assertIsArray($feature);
            self::assertIsArray($feature['properties']);
            self::assertSame(['landType'], array_keys($feature['properties']));
        }
    }

    #[Test]
    public function it_keeps_every_tile_within_a_small_budget(): void
    {
        $built = $this->builder()->build([$this->fixtureParcels()], new TileSizeBudget(1000));

        $largest = new PDO('sqlite:' . $built->path)->query('SELECT MAX(LENGTH(tile_data)) FROM tiles');
        self::assertInstanceOf(PDOStatement::class, $largest);
        self::assertLessThanOrEqual(1000, (int) $largest->fetchColumn());
    }

    #[Test]
    public function it_drops_parcels_instead_of_merging_them_when_a_tile_is_over_budget(): void
    {
        $built = $this->builder()->build([$this->fixtureParcels()], new TileSizeBudget(1000));

        $originalBoxes = $this->boundingBoxesOfInputParcels();
        $decoded = json_decode($this->runTool(['tippecanoe-decode', $built->path]), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['features']);

        $checked = 0;
        $worstExtension = 0.0;
        foreach ($decoded['features'] as $tile) {
            self::assertIsArray($tile);
            self::assertIsArray($tile['properties']);
            if ($tile['properties']['zoom'] < 14) {
                continue;
            }
            foreach (self::featuresOf($tile) as $feature) {
                self::assertIsArray($feature);
                $id = $feature['id'] ?? null;
                self::assertIsInt($id, 'Every parcel in a tile at zoom 14 or more keeps its id.');
                self::assertArrayHasKey($id, $originalBoxes);
                self::assertIsArray($feature['geometry']);
                $box = self::boundingBox($feature['geometry']['coordinates']);
                $original = $originalBoxes[$id];
                $worstExtension = max(
                    $worstExtension,
                    $original[0] - $box[0],
                    $original[1] - $box[1],
                    $box[2] - $original[2],
                    $box[3] - $original[3],
                );
                ++$checked;
            }
        }

        self::assertGreaterThan(0, $checked);
        // Merging parcels (coalescing) extends a feature up to 0.0003 degrees beyond its own parcel; dropping only
        // leaves a rounding error of the tile grid.
        self::assertLessThan(0.00001, $worstExtension);
    }

    #[Test]
    public function it_reports_an_unreadable_input(): void
    {
        $path = $this->work->path . '/broken.geojsonl';
        file_put_contents($path, "this is not json\n");

        $this->expectException(TileBuildFailed::class);

        $this->builder()->build([new ConvertedParcels(new MunicipalityCode(572659), $path, [new AreaParcelCount(new CadastralAreaCode(659541), 1)])], TileSizeBudget::smoothMap());
    }

    private function builder(): TilesetBuilderUsingTippecanoe
    {
        return new TilesetBuilderUsingTippecanoe(new ProcessRunner(), $this->work->path);
    }

    private function fixtureParcels(): ConvertedParcels
    {
        return new ConvertedParcels(
            new MunicipalityCode(572659),
            dirname(__DIR__, 3) . '/Fixtures/geojson/572659.geojsonl',
            [new AreaParcelCount(new CadastralAreaCode(659541), 22), new AreaParcelCount(new CadastralAreaCode(740225), 3)],
        );
    }

    /**
     * @param non-empty-list<string> $command
     */
    private function runTool(array $command): string
    {
        $result = new ProcessRunner()->run($command);
        self::assertSame(0, $result->exitCode, $result->stderr);

        return $result->stdout . $result->stderr;
    }

    /**
     * @return array<int, array{float, float, float, float}> west, south, east, north by parcel id
     */
    private function boundingBoxesOfInputParcels(): array
    {
        $boxes = [];
        foreach (file($this->fixtureParcels()->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $feature = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($feature);
            self::assertIsArray($feature['properties']);
            self::assertIsArray($feature['geometry']);
            $id = $feature['properties']['id'];
            self::assertIsInt($id);
            $boxes[$id] = self::boundingBox($feature['geometry']['coordinates']);
        }

        return $boxes;
    }

    /**
     * @param array<mixed> $tile a decoded tile: a collection of one layer collection
     * @return list<mixed>
     */
    private static function featuresOf(array $tile): array
    {
        $layers = $tile['features'];
        self::assertIsArray($layers);
        $layer = $layers[0];
        self::assertIsArray($layer);
        $features = $layer['features'] ?? [];
        self::assertIsArray($features);

        return array_values($features);
    }

    /**
     * @param mixed $coordinates nested GeoJSON coordinate arrays
     * @return array{float, float, float, float} west, south, east, north
     */
    private static function boundingBox(mixed $coordinates): array
    {
        $points = self::points($coordinates);
        if ($points === []) {
            self::fail('A geometry without coordinates.');
        }
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * @return list<array{float, float}>
     */
    private static function points(mixed $coordinates): array
    {
        self::assertIsArray($coordinates);
        if (is_numeric($coordinates[0])) {
            self::assertIsNumeric($coordinates[1]);

            return [[(float) $coordinates[0], (float) $coordinates[1]]];
        }

        return array_merge(...array_map(self::points(...), array_values($coordinates)));
    }
}
