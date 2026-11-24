<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use ParcelMap\Tests\Support\FixtureParcelStore;
use ParcelMap\Tests\Support\FixtureTileset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase
{
    private const string IMMUTABLE = 'public, max-age=31536000, immutable';

    #[Test]
    public function it_finds_a_building_parcel_by_its_number_with_the_st_prefix(): void
    {
        $result = $this->search('st. 72');

        self::assertSame(200, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
        self::assertSame(self::IMMUTABLE, $result->header('Cache-Control'));
        self::assertSame(
            [
                'places' => [],
                'parcels' => [
                    [
                        'id' => FixtureParcelStore::BUILDING_ID,
                        'parcelNumber' => 'st. 72',
                        'kind' => 'building',
                        'cadastralArea' => ['code' => 659541, 'name' => 'Jičín'],
                        'municipality' => ['code' => 572659, 'name' => 'Jičín'],
                    ],
                ],
            ],
            json_decode($result->body, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param list<int> $ids
     */
    #[Test]
    #[DataProvider('numbers')]
    public function it_finds_parcels_by_number(string $query, array $ids): void
    {
        self::assertSame($ids, $this->parcelIds($this->search($query)));
    }

    /**
     * @return array<string, array{string, list<int>}>
     */
    public static function numbers(): array
    {
        return [
            'subdivision' => ['st. 80/2', [1753304604]],
            'land with holes' => ['1171', [FixtureParcelStore::LAND_WITH_HOLES_ID]],
            'number and a start of the area name' => ['867/121 rob', [FixtureParcelStore::ROBOUSY_ID]],
            'area code that does not fit' => ['867/121 659541', []],
            'a name without a number' => ['jicin', []],
        ];
    }

    #[Test]
    public function it_takes_the_map_centre_into_account(): void
    {
        $result = $this->search('st. 72', '15.351,50.437');

        self::assertSame([FixtureParcelStore::BUILDING_ID], $this->parcelIds($result));
    }

    #[Test]
    #[DataProvider('badRequests')]
    public function it_answers_400_for_a_bad_request(string $queryString): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/api/search/' . FixtureTileset::VERSION . $queryString);

        self::assertSame(400, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
        self::assertSame('{"error":"bad_request"}', $result->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badRequests(): array
    {
        return [
            'no q' => [''],
            'blank q' => ['?q=%20%20'],
            'q of 101 characters' => ['?q=' . str_repeat('a', 101)],
            'near is not numbers' => ['?q=st.%2072&near=abc'],
            'near is out of range' => ['?q=st.%2072&near=200,50'],
            'near has one number' => ['?q=st.%2072&near=15.35'],
            'q is a list' => ['?q[]=72'],
        ];
    }

    #[Test]
    #[DataProvider('notFound')]
    public function it_answers_404_for_another_or_malformed_version(string $version): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/api/search/' . $version . '?q=72');

        self::assertSame(404, $result->status);
        self::assertSame('{"error":"not_found"}', $result->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notFound(): array
    {
        return ['another version' => ['19990101000000'], 'malformed version' => ['not_a_version']];
    }

    #[Test]
    public function it_rejects_a_post_with_405_and_the_allowed_method(): void
    {
        $result = HttpClient::fromEnvironment()->request('POST', '/api/search/' . FixtureTileset::VERSION . '?q=72');

        self::assertSame(405, $result->status);
        self::assertSame('GET', $result->header('Allow'));
        self::assertSame('{"error":"method_not_allowed"}', $result->body);
    }

    private function search(string $query, ?string $near = null): HttpResult
    {
        $path = '/api/search/' . FixtureTileset::VERSION . '?q=' . rawurlencode($query);

        return HttpClient::fromEnvironment()->request('GET', $near === null ? $path : $path . '&near=' . $near);
    }

    /**
     * @return list<int>
     */
    private function parcelIds(HttpResult $result): array
    {
        self::assertSame(200, $result->status, $result->body);
        $body = json_decode($result->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['parcels']);

        return array_values(array_map(static fn(mixed $parcel): int => is_array($parcel) && is_int($parcel['id']) ? $parcel['id'] : -1, $body['parcels']));
    }
}
