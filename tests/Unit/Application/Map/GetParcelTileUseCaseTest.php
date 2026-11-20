<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Map;

use ParcelMap\Application\Map\GetParcelTileUseCase;
use ParcelMap\Application\Map\ParcelTileInput;
use ParcelMap\Application\Map\ParcelTileNotFound;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Map\VectorTile;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use ParcelMap\Tests\Support\Gzip;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetParcelTileUseCaseTest extends TestCase
{
    private const string VERSION = '20261120093000';

    #[Test]
    public function it_returns_the_bytes_of_an_existing_tile(): void
    {
        $data = Gzip::encode('tile');

        $output = $this->useCase(['12/2222/1381' => VectorTile::fromGzippedData($data)])
            ->execute(new ParcelTileInput(self::VERSION, 12, 2222, 1381));

        self::assertFalse($output->empty);
        self::assertSame($data, $output->gzippedData);
    }

    #[Test]
    public function it_reports_a_tile_without_data_as_empty(): void
    {
        $output = $this->useCase([])->execute(new ParcelTileInput(self::VERSION, 12, 2222, 1381));

        self::assertTrue($output->empty);
        self::assertSame('', $output->gzippedData);
    }

    #[Test]
    public function it_reports_zoom_levels_outside_the_tileset_as_empty_even_when_a_tile_exists(): void
    {
        $tile = VectorTile::fromGzippedData(Gzip::encode('tile'));
        $useCase = $this->useCase(['8/1/1' => $tile, '17/1/1' => $tile]);

        self::assertTrue($useCase->execute(new ParcelTileInput(self::VERSION, 8, 1, 1))->empty);
        self::assertTrue($useCase->execute(new ParcelTileInput(self::VERSION, 17, 1, 1))->empty);
    }

    #[Test]
    public function it_rejects_a_version_that_is_not_the_current_one(): void
    {
        $this->expectException(ParcelTileNotFound::class);

        $this->useCase([])->execute(new ParcelTileInput('19990101000000', 12, 2222, 1381));
    }

    #[Test]
    public function it_rejects_a_malformed_version(): void
    {
        $this->expectException(ParcelTileNotFound::class);

        $this->useCase([])->execute(new ParcelTileInput('not/a/version', 12, 2222, 1381));
    }

    #[Test]
    public function it_rejects_a_coordinate_outside_the_tile_grid(): void
    {
        $this->expectException(ParcelTileNotFound::class);

        $this->useCase([])->execute(new ParcelTileInput(self::VERSION, 12, 4096, 0));
    }

    #[Test]
    public function it_rejects_every_request_when_no_data_is_imported(): void
    {
        $useCase = new GetParcelTileUseCase(new ParcelTilesetRepositoryUsingMemory(null));

        $this->expectException(ParcelTileNotFound::class);

        $useCase->execute(new ParcelTileInput(self::VERSION, 12, 2222, 1381));
    }

    /**
     * @param array<string, VectorTile> $tiles
     */
    private function useCase(array $tiles): GetParcelTileUseCase
    {
        $tileset = new ParcelTileset(
            new TilesetVersion(self::VERSION),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            9,
            16,
        );

        return new GetParcelTileUseCase(new ParcelTilesetRepositoryUsingMemory($tileset, $tiles));
    }
}
