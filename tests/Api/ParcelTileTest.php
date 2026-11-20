<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use ParcelMap\Tests\Support\FixtureTileset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelTileTest extends TestCase
{
    private const string IMMUTABLE = 'public, max-age=31536000, immutable';

    #[Test]
    public function it_serves_a_gzipped_tile_to_a_client_that_accepts_gzip(): void
    {
        $result = $this->tile('12/2222/1381', ['Accept-Encoding' => 'gzip']);

        self::assertSame(200, $result->status);
        self::assertSame('application/vnd.mapbox-vector-tile', $result->header('Content-Type'));
        self::assertSame('gzip', $result->header('Content-Encoding'));
        self::assertSame(self::IMMUTABLE, $result->header('Cache-Control'));
        self::assertSame('Accept-Encoding', $result->header('Vary'));
        self::assertSame('1f8b', bin2hex(substr($result->body, 0, 2)));
    }

    #[Test]
    public function it_serves_an_uncompressed_tile_to_a_client_that_does_not_accept_gzip(): void
    {
        $result = $this->tile('12/2222/1381');

        self::assertSame(200, $result->status);
        self::assertNull($result->header('Content-Encoding'));
        self::assertSame(self::IMMUTABLE, $result->header('Cache-Control'));
        self::assertNotSame('', $result->body);
        self::assertNotSame('1f8b', bin2hex(substr($result->body, 0, 2)));
    }

    #[Test]
    public function it_answers_204_for_a_tile_without_data(): void
    {
        $result = $this->tile('14/8891/5525', ['Accept-Encoding' => 'gzip']);

        self::assertSame(204, $result->status);
        self::assertSame('', $result->body);
        self::assertSame(self::IMMUTABLE, $result->header('Cache-Control'));
    }

    #[Test]
    #[DataProvider('notFound')]
    public function it_answers_404_for_an_unknown_version_a_bad_coordinate_or_a_wrong_extension(string $path): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', $path);

        self::assertSame(404, $result->status);
        self::assertSame('{"error":"not_found"}', $result->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notFound(): array
    {
        $base = '/tiles/parcels/' . FixtureTileset::VERSION;

        return [
            'another version' => ['/tiles/parcels/19990101000000/12/2222/1381.pbf'],
            'x out of range' => [$base . '/12/4096/0.pbf'],
            'x not a number' => [$base . '/12/x/1381.pbf'],
            'wrong extension' => [$base . '/12/2222/1381.png'],
        ];
    }

    #[Test]
    public function it_rejects_a_post_to_a_tile_url(): void
    {
        $result = HttpClient::fromEnvironment()->request(
            'POST',
            '/tiles/parcels/' . FixtureTileset::VERSION . '/12/2222/1381.pbf',
        );

        self::assertSame(405, $result->status);
        self::assertSame('GET', $result->header('Allow'));
    }

    /**
     * @param array<string, string> $headers
     */
    private function tile(string $zxy, array $headers = []): HttpResult
    {
        return HttpClient::fromEnvironment()->request(
            'GET',
            '/tiles/parcels/' . FixtureTileset::VERSION . '/' . $zxy . '.pbf',
            $headers,
        );
    }
}
