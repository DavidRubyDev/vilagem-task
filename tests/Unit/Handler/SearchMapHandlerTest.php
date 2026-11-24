<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

use ParcelMap\Application\Search\SearchMapUseCase;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Search\ParcelMatch;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\SearchMapHandler;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use ParcelMap\Tests\Fake\SearchIndexRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchMapHandlerTest extends TestCase
{
    private const string VERSION = '20261120093000';

    #[Test]
    public function it_answers_the_results_as_immutable_json(): void
    {
        $response = $this->handler()->handle($this->request(['q' => 'st. 72']));

        self::assertSame(200, $response->status);
        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertSame('public, max-age=31536000, immutable', $response->headers['Cache-Control']);
        self::assertSame(
            [
                'places' => [],
                'parcels' => [
                    [
                        'id' => 1753295604,
                        'parcelNumber' => 'st. 72',
                        'kind' => 'building',
                        'cadastralArea' => ['code' => 659541, 'name' => 'Jičín'],
                        'municipality' => ['code' => 572659, 'name' => 'Jičín'],
                    ],
                ],
            ],
            json_decode($response->body, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_answers_empty_lists_when_nothing_matches(): void
    {
        $response = $this->handler()->handle($this->request(['q' => '5']));

        self::assertSame(200, $response->status);
        self::assertSame('{"places":[],"parcels":[]}', $response->body);
    }

    #[Test]
    #[DataProvider('validNears')]
    public function it_accepts_near_as_longitude_and_latitude(string $near): void
    {
        self::assertSame(200, $this->handler()->handle($this->request(['q' => '72', 'near' => $near]))->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNears(): array
    {
        return [
            'three decimals' => ['15.351,50.437'],
            'eight decimals' => ['15.35106597,50.43737683'],
            'integers' => ['15,50'],
            'negative' => ['-15.5,-50.5'],
        ];
    }

    /**
     * @param array<string, string> $query
     */
    #[Test]
    #[DataProvider('badQueries')]
    public function it_answers_400_for_a_missing_or_invalid_parameter(array $query): void
    {
        $response = $this->handler()->handle($this->request($query));

        self::assertSame(400, $response->status);
        self::assertSame('{"error":"bad_request"}', $response->body);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function badQueries(): array
    {
        return [
            'no q' => [[]],
            'blank q' => [['q' => '  ']],
            'q too long' => [['q' => str_repeat('a', 101)]],
            'near without comma' => [['q' => '72', 'near' => '15.35']],
            'near with letters' => [['q' => '72', 'near' => 'abc']],
            'near with spaces' => [['q' => '72', 'near' => '15.35, 50.43']],
            'near with nine decimals' => [['q' => '72', 'near' => '15.123456789,50.4']],
            'near with a third number' => [['q' => '72', 'near' => '15,50,3']],
            'near out of range' => [['q' => '72', 'near' => '200,50']],
            'empty near' => [['q' => '72', 'near' => '']],
        ];
    }

    #[Test]
    public function it_answers_404_for_another_version(): void
    {
        $request = new Request('GET', '/api/search/19990101000000', [], ['version' => '19990101000000'], ['q' => '72']);

        $response = $this->handler()->handle($request);

        self::assertSame(404, $response->status);
        self::assertSame('{"error":"not_found"}', $response->body);
    }

    /**
     * @param array<string, string> $query
     */
    private function request(array $query): Request
    {
        return new Request('GET', '/api/search/' . self::VERSION, [], ['version' => self::VERSION], $query);
    }

    private function handler(): SearchMapHandler
    {
        $tileset = new ParcelTileset(
            new TilesetVersion(self::VERSION),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            9,
            16,
        );
        $match = new ParcelMatch(
            new ParcelId(1753295604),
            new ParcelNumber(ParcelKind::Building, 72, null),
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín')),
        );

        return new SearchMapHandler(new SearchMapUseCase(
            new ParcelTilesetRepositoryUsingMemory($tileset),
            new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), [$match]),
        ));
    }
}
