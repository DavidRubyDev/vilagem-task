<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use ParcelMap\Tests\Support\FixtureParcelStore;
use ParcelMap\Tests\Support\FixtureTileset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelDetailTest extends TestCase
{
    private const string IMMUTABLE = 'public, max-age=31536000, immutable';

    #[Test]
    public function it_serves_the_details_of_a_building_parcel_with_its_boundary(): void
    {
        $result = $this->parcel((string) FixtureParcelStore::BUILDING_ID);

        self::assertSame(200, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
        self::assertSame(self::IMMUTABLE, $result->header('Cache-Control'));
        $body = $this->decode($result);
        self::assertSame(FixtureParcelStore::BUILDING_ID, $body['id']);
        self::assertSame('st. 72', $body['parcelNumber']);
        self::assertSame('building', $body['kind']);
        self::assertSame(['code' => 659541, 'name' => 'Jičín'], $body['cadastralArea']);
        self::assertSame(['code' => 572659, 'name' => 'Jičín'], $body['municipality']);
        self::assertSame(13, $body['landTypeCode']);
        self::assertNull($body['landUseCode']);
        self::assertSame(455, $body['areaSquareMetres']);
        self::assertSame(
            'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1753295604',
            $body['cadastreViewerUrl'],
        );
        $boundary = $body['boundary'];
        self::assertIsArray($boundary);
        self::assertSame('Polygon', $boundary['type']);
        $rings = $boundary['coordinates'];
        self::assertIsArray($rings);
        self::assertCount(1, $rings);
        self::assertIsArray($rings[0]);
        self::assertCount(32, $rings[0]);
        self::assertSame([15.3509779, 50.4377444], $rings[0][0]);
    }

    #[Test]
    public function it_serves_a_land_parcel_with_a_land_use_and_holes(): void
    {
        $body = $this->decode($this->parcel((string) FixtureParcelStore::LAND_WITH_HOLES_ID));

        self::assertSame('1171', $body['parcelNumber']);
        self::assertSame('land', $body['kind']);
        self::assertSame(17, $body['landUseCode']);
        self::assertSame(14, $body['landTypeCode']);
        self::assertSame(13077, $body['areaSquareMetres']);
        $boundary = $body['boundary'];
        self::assertIsArray($boundary);
        self::assertIsArray($boundary['coordinates']);
        self::assertCount(3, $boundary['coordinates']);
    }

    #[Test]
    public function it_serves_a_parcel_of_another_cadastral_area_of_the_municipality(): void
    {
        $body = $this->decode($this->parcel((string) FixtureParcelStore::ROBOUSY_ID));

        self::assertSame('867/121', $body['parcelNumber']);
        self::assertSame(['code' => 740225, 'name' => 'Robousy'], $body['cadastralArea']);
        self::assertSame(['code' => 572659, 'name' => 'Jičín'], $body['municipality']);
    }

    #[Test]
    #[DataProvider('notFound')]
    public function it_answers_404_for_an_unknown_parcel_version_or_malformed_id(string $path): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', $path);

        self::assertSame(404, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
        self::assertSame('{"error":"not_found"}', $result->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notFound(): array
    {
        $base = '/api/parcels/' . FixtureTileset::VERSION;

        return [
            'unknown id' => [$base . '/999'],
            'another version' => ['/api/parcels/19990101000000/' . FixtureParcelStore::BUILDING_ID],
            'id not a number' => [$base . '/abc'],
            'id with 16 digits' => [$base . '/1234567890123456'],
            'id zero' => [$base . '/0'],
        ];
    }

    #[Test]
    public function it_rejects_a_post_with_405_and_the_allowed_method(): void
    {
        $result = HttpClient::fromEnvironment()->request('POST', '/api/parcels/' . FixtureTileset::VERSION . '/' . FixtureParcelStore::BUILDING_ID);

        self::assertSame(405, $result->status);
        self::assertSame('GET', $result->header('Allow'));
        self::assertSame('{"error":"method_not_allowed"}', $result->body);
    }

    private function parcel(string $id): HttpResult
    {
        return HttpClient::fromEnvironment()->request('GET', '/api/parcels/' . FixtureTileset::VERSION . '/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(HttpResult $result): array
    {
        $body = json_decode($result->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }
}
