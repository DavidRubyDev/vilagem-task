<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use ParcelMap\Tests\Support\FixtureTileset;
use ParcelMap\Tests\Support\LocalHttpServer;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MapConfigurationTest extends TestCase
{
    #[Test]
    public function it_serves_the_initial_view_the_base_map_and_the_parcel_layer(): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/api/map-config');

        self::assertSame(200, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
        self::assertSame('no-cache', $result->header('Cache-Control'));
        self::assertEquals(
            [
                'initialView' => [
                    'center' => ['longitude' => 15.351653, 'latitude' => 50.437045],
                    'zoom' => 12,
                ],
                'baseMap' => [
                    'tileUrlTemplate' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    'tileSize' => 256,
                    'maxZoom' => 19,
                    'attribution' => [
                        'text' => '© OpenStreetMap contributors',
                        'url' => 'https://www.openstreetmap.org/copyright',
                    ],
                ],
                'parcels' => [
                    'tileUrlTemplate' => '/tiles/parcels/' . FixtureTileset::VERSION . '/{z}/{x}/{y}.pbf',
                    'parcelUrlTemplate' => '/api/parcels/' . FixtureTileset::VERSION . '/{id}',
                    'searchUrl' => '/api/search/' . FixtureTileset::VERSION,
                    'minZoom' => FixtureTileset::MIN_ZOOM,
                    'maxZoom' => FixtureTileset::MAX_ZOOM,
                    'bounds' => [
                        'west' => FixtureTileset::WEST,
                        'south' => FixtureTileset::SOUTH,
                        'east' => FixtureTileset::EAST,
                        'north' => FixtureTileset::NORTH,
                    ],
                    'attribution' => [
                        'text' => 'ČÚZK, 2026',
                        'url' => 'https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx',
                    ],
                ],
            ],
            json_decode($result->body, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_sends_no_search_url_when_the_search_index_of_the_current_version_is_missing(): void
    {
        $dataDir = TemporaryDirectory::create();
        $fixtures = dirname(__DIR__) . '/Fixtures/data';
        mkdir($dataDir->path . '/tiles');
        mkdir($dataDir->path . '/parcels');
        copy($fixtures . '/tiles/parcels.mbtiles', $dataDir->path . '/tiles/parcels.mbtiles');
        copy($fixtures . '/parcels/' . FixtureTileset::VERSION . '.sqlite', $dataDir->path . '/parcels/' . FixtureTileset::VERSION . '.sqlite');
        $server = LocalHttpServer::start(dirname(__DIR__, 2) . '/public/index.php', ['PARCEL_MAP_DATA_DIR' => $dataDir->path]);

        try {
            $result = new HttpClient(rtrim($server->baseUrl(), '/'))->request('GET', '/api/map-config');
        } finally {
            $server->stop();
            $dataDir->remove();
        }

        self::assertSame(200, $result->status);
        $body = json_decode($result->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['parcels']);
        self::assertArrayHasKey('searchUrl', $body['parcels']);
        self::assertNull($body['parcels']['searchUrl']);
        self::assertSame('/api/parcels/' . FixtureTileset::VERSION . '/{id}', $body['parcels']['parcelUrlTemplate']);
    }

    #[Test]
    public function it_rejects_a_post_with_405_and_the_allowed_method(): void
    {
        $result = HttpClient::fromEnvironment()->request('POST', '/api/map-config');

        self::assertSame(405, $result->status);
        self::assertSame('GET', $result->header('Allow'));
        self::assertSame('{"error":"method_not_allowed"}', $result->body);
    }

    #[Test]
    public function it_does_not_match_a_trailing_slash(): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/api/map-config/');

        self::assertSame(404, $result->status);
    }
}
