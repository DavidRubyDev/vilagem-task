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
    public function it_finds_an_address_and_names_the_parcel_its_building_stands_on(): void
    {
        $body = $this->decode($this->search('Valdštejnovo náměstí 58'));

        self::assertSame(
            [
                [
                    'kind' => 'address',
                    'code' => 25344951,
                    'name' => 'Valdštejnovo náměstí 58',
                    'context' => 'Staré Město, Jičín',
                    'center' => ['longitude' => 15.351066, 'latitude' => 50.4373768],
                    'bounds' => null,
                    'parcelId' => FixtureParcelStore::BUILDING_ID,
                ],
            ],
            $body['places'],
        );
        self::assertSame([], $body['parcels']);
    }

    #[Test]
    public function it_finds_a_word_prefix_of_the_street_among_the_addresses_in_key_order_without_near(): void
    {
        $body = $this->decode($this->search('valdstejnovo 5'));

        self::assertSame(
            ['Valdštejnovo náměstí 57', 'Valdštejnovo náměstí 58', 'Valdštejnovo náměstí 59'],
            $this->placeNames($body),
        );
    }

    #[Test]
    public function it_finds_the_municipality_once_with_its_bounds_and_not_the_cadastral_area_of_the_same_name(): void
    {
        $body = $this->decode($this->search('jicin'));

        $places = $this->places($body);
        self::assertSame('municipality', $places[0]['kind']);
        self::assertSame(572659, $places[0]['code']);
        self::assertSame('Jičín', $places[0]['name']);
        self::assertSame('', $places[0]['context']);
        self::assertIsArray($places[0]['bounds']);
        self::assertSame(['west', 'south', 'east', 'north'], array_keys($places[0]['bounds']));
        self::assertNull($places[0]['parcelId']);
        self::assertSame(['municipality', 'cadastralArea'], array_slice(array_column($places, 'kind'), 0, 2));
        self::assertNotContains(659541, array_column($places, 'code'));
        self::assertSame([], $body['parcels']);
    }

    #[Test]
    public function it_finds_the_cadastral_area_with_its_bounds_and_not_the_part_of_the_same_name(): void
    {
        $places = $this->places($this->decode($this->search('robousy')));

        self::assertSame('cadastralArea', $places[0]['kind']);
        self::assertSame(740225, $places[0]['code']);
        self::assertSame('Jičín', $places[0]['context']);
        self::assertIsArray($places[0]['bounds']);
        self::assertNotContains('municipalityPart', array_column($places, 'kind'));
    }

    #[Test]
    public function it_finds_a_part_of_a_municipality_by_a_start_of_its_name(): void
    {
        $places = $this->places($this->decode($this->search('stare')));

        self::assertSame('municipalityPart', $places[0]['kind']);
        self::assertSame('Staré Město', $places[0]['name']);
        self::assertSame('Jičín', $places[0]['context']);
        self::assertIsArray($places[0]['bounds']);
    }

    #[Test]
    public function it_finds_a_street_with_its_bounds(): void
    {
        $places = $this->places($this->decode($this->search('Palackého')));

        self::assertSame('street', $places[0]['kind']);
        self::assertSame('Palackého', $places[0]['name']);
        self::assertSame('Jičín', $places[0]['context']);
        self::assertIsArray($places[0]['bounds']);
    }

    #[Test]
    public function it_does_not_find_a_street_without_a_covered_address(): void
    {
        $body = $this->decode($this->search('17. listopadu'));

        self::assertSame([], $body['places']);
    }

    #[Test]
    public function it_finds_an_address_of_a_cadastral_area_without_a_street(): void
    {
        $body = $this->decode($this->search('robousy 239'));

        $places = $this->places($body);
        self::assertCount(1, $places);
        self::assertSame('Robousy 239', $places[0]['name']);
        self::assertSame('Jičín', $places[0]['context']);
        self::assertSame(95522753010, $places[0]['parcelId']);
        self::assertSame([], $body['parcels']);
    }

    #[Test]
    public function it_does_not_find_an_address_whose_building_stands_outside_the_covered_parcels(): void
    {
        $body = $this->decode($this->search('Robousy 1'));

        self::assertNotContains(17482674, array_column($this->places($body), 'code'));
    }

    #[Test]
    public function it_orders_equal_matches_by_the_distance_from_near(): void
    {
        $near = $this->decode($this->search('Palackého', '15.3512,50.4372'));
        $far = $this->decode($this->search('Palackého', '15.4,50.4'));

        // The street is a better match than its addresses wherever near is; the addresses are equal matches.
        self::assertSame('street', $this->places($near)[0]['kind']);
        self::assertSame('street', $this->places($far)[0]['kind']);
        self::assertEqualsCanonicalizing($this->placeNames($near), $this->placeNames($far));
        self::assertNotSame($this->placeNames($near), $this->placeNames($far));
    }

    #[Test]
    public function it_searches_both_when_a_text_has_a_number_and_a_word(): void
    {
        $body = $this->decode($this->search('867/121 rob'));

        self::assertSame([FixtureParcelStore::ROBOUSY_ID], $this->parcelIds($this->search('867/121 rob')));
        self::assertSame([], $body['places']);
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
     * @return array<string, mixed>
     */
    private function decode(HttpResult $result): array
    {
        self::assertSame(200, $result->status, $result->body);
        $body = json_decode($result->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    private function places(array $body): array
    {
        self::assertIsArray($body['places']);

        /** @var list<array<string, mixed>> $places */
        $places = $body['places'];

        return $places;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<mixed>
     */
    private function placeNames(array $body): array
    {
        return array_column($this->places($body), 'name');
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
