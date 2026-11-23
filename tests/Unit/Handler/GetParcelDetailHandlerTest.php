<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

use ParcelMap\Application\Parcel\GetParcelDetailUseCase;
use ParcelMap\Domain\Cadastre\Area;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\LandType;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Handler\GetParcelDetailHandler;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Tests\Fake\ParcelRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetParcelDetailHandlerTest extends TestCase
{
    private const string VERSION = '20261120093000';

    #[Test]
    #[DataProvider('badIds')]
    public function it_answers_404_for_an_id_that_is_not_one_to_fifteen_digits(string $id): void
    {
        $response = $this->handler()->handle($this->request($id));

        self::assertSame(404, $response->status);
        self::assertSame('{"error":"not_found"}', $response->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badIds(): array
    {
        return ['letters' => ['abc'], 'sixteen digits' => ['1234567890123456'], 'empty' => [''], 'signed' => ['-5'], 'decimal' => ['1.5']];
    }

    #[Test]
    public function it_answers_404_for_an_unknown_parcel(): void
    {
        self::assertSame(404, $this->handler()->handle($this->request('999'))->status);
    }

    #[Test]
    public function it_answers_the_details_as_immutable_json_with_a_polygon_boundary(): void
    {
        $response = $this->handler()->handle($this->request('1753295604'));

        self::assertSame(200, $response->status);
        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertSame('public, max-age=31536000, immutable', $response->headers['Cache-Control']);
        self::assertSame(
            [
                'id' => 1753295604,
                'parcelNumber' => 'st. 72',
                'kind' => 'building',
                'cadastralArea' => ['code' => 659541, 'name' => 'Jičín'],
                'municipality' => ['code' => 572659, 'name' => 'Jičín'],
                'landTypeCode' => 13,
                'landUseCode' => null,
                'areaSquareMetres' => 455,
                'boundary' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[15.35, 50.43], [15.36, 50.43], [15.36, 50.44], [15.35, 50.43]]],
                ],
                'cadastreViewerUrl' => 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1753295604',
                'bounds' => ['west' => 15.35, 'south' => 50.43, 'east' => 15.36, 'north' => 50.44],
            ],
            json_decode($response->body, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function request(string $id): Request
    {
        return new Request('GET', '/api/parcels', [], ['version' => self::VERSION, 'id' => $id]);
    }

    private function handler(): GetParcelDetailHandler
    {
        $start = new Coordinates(15.35, 50.43);
        $parcel = new Parcel(
            new ParcelId(1753295604),
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín')),
            new ParcelNumber(ParcelKind::Building, 72, null),
            new LandType(13),
            null,
            new Area(455),
            ParcelBoundary::fromRings([[$start, new Coordinates(15.36, 50.43), new Coordinates(15.36, 50.44), $start]]),
        );
        $tileset = new ParcelTileset(
            new TilesetVersion(self::VERSION),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            9,
            16,
        );

        return new GetParcelDetailHandler(new GetParcelDetailUseCase(
            new ParcelTilesetRepositoryUsingMemory($tileset),
            new ParcelRepositoryUsingMemory(new TilesetVersion(self::VERSION), $parcel),
        ));
    }
}
