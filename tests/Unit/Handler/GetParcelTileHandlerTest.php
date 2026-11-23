<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

use ParcelMap\Application\Map\GetParcelTileUseCase;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Map\VectorTile;
use ParcelMap\Handler\GetParcelTileHandler;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\StoredTileCorrupt;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetParcelTileHandlerTest extends TestCase
{
    private const string VERSION = '20261120093000';
    // Passes the gzip magic-byte check of VectorTile but cannot be decompressed.
    private const string CORRUPT = "\x1f\x8b" . 'corrupt';

    #[Test]
    public function it_reports_a_stored_tile_that_cannot_be_decompressed(): void
    {
        $this->expectException(StoredTileCorrupt::class);

        $this->handler()->handle($this->request());
    }

    #[Test]
    public function it_passes_a_stored_tile_through_unchanged_to_a_client_that_accepts_gzip(): void
    {
        $response = $this->handler()->handle($this->request(['accept-encoding' => 'gzip']));

        self::assertSame(200, $response->status);
        self::assertSame('gzip', $response->headers['Content-Encoding']);
        self::assertSame(self::CORRUPT, $response->body);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): Request
    {
        return new Request('GET', '/tiles', $headers, ['version' => self::VERSION, 'z' => '12', 'x' => '2222', 'y' => '1381']);
    }

    private function handler(): GetParcelTileHandler
    {
        $tileset = new ParcelTileset(
            new TilesetVersion(self::VERSION),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            9,
            16,
        );
        $tiles = ['12/2222/1381' => VectorTile::fromGzippedData(self::CORRUPT)];

        return new GetParcelTileHandler(new GetParcelTileUseCase(new ParcelTilesetRepositoryUsingMemory($tileset, $tiles)));
    }
}
