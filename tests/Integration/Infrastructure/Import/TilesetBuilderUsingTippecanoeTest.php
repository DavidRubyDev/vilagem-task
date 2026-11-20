<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\TileBuildFailed;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Infrastructure\Import\TilesetBuilderUsingTippecanoe;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Tests\Support\TemporaryDirectory;
use ParcelMap\Tests\Support\MbtilesMetadata;
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
        $built = $this->builder()->build([$this->fixtureParcels()]);

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
        $built = $this->builder()->build([$this->fixtureParcels()]);

        $decoded = $this->runTool(['tippecanoe-decode', $built->path, '14', '8890', '5525']);

        self::assertStringContainsString('"id": 1753295604', $decoded);
        self::assertStringContainsString('landType', $decoded);
    }

    #[Test]
    public function it_reports_an_unreadable_input(): void
    {
        $path = $this->work->path . '/broken.geojsonl';
        file_put_contents($path, "this is not json\n");

        $this->expectException(TileBuildFailed::class);

        $this->builder()->build([new ConvertedParcels(new CadastralAreaCode(659541), $path, 1)]);
    }

    private function builder(): TilesetBuilderUsingTippecanoe
    {
        return new TilesetBuilderUsingTippecanoe(new ProcessRunner(), $this->work->path);
    }

    private function fixtureParcels(): ConvertedParcels
    {
        return new ConvertedParcels(
            new CadastralAreaCode(659541),
            dirname(__DIR__, 3) . '/Fixtures/geojson/659541.geojsonl',
            22,
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
}
