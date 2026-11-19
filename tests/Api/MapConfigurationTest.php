<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MapConfigurationTest extends TestCase
{
    #[Test]
    public function it_serves_the_initial_view_and_the_base_map(): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/api/map-config');

        self::assertSame(200, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
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
            ],
            json_decode($result->body, true, flags: JSON_THROW_ON_ERROR),
        );
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
