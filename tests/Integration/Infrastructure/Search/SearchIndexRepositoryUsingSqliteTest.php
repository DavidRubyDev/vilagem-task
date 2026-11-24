<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Search;

use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\ConvertedPlaces;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Search\PlaceKind;
use ParcelMap\Domain\Search\SearchIndexNotPublished;
use ParcelMap\Domain\Search\SearchIndexUnreadable;
use ParcelMap\Domain\Search\SearchQuery;
use ParcelMap\Infrastructure\Import\SearchIndexBuilderUsingSqlite;
use ParcelMap\Infrastructure\Search\SearchIndexRepositoryUsingSqlite;
use ParcelMap\Tests\Support\PlaceFile;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchIndexRepositoryUsingSqliteTest extends TestCase
{
    private const string VERSION = '20261120093000';
    private const int JICIN = 659541;
    private const int ROBOUSY = 740225;
    private const int BECHARY = 601462;

    private TemporaryDirectory $work;

    protected function setUp(): void
    {
        $this->work = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->work->remove();
    }

    #[Test]
    public function it_finds_the_parcels_with_exactly_the_number(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 72, null),
            self::line(2, self::JICIN, 1, 72, null),
            self::line(3, self::JICIN, 2, 72, 1),
            self::line(4, self::JICIN, 2, 73, null),
            self::line(5, self::JICIN, 2, 7, null),
        ]);

        self::assertSame([1, 2], $this->ids($repository, '72'));
    }

    #[Test]
    public function it_matches_the_subdivision_exactly_and_no_subdivision_means_none(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 80, null),
            self::line(2, self::JICIN, 2, 80, 2),
            self::line(3, self::JICIN, 2, 80, 22),
        ]);

        self::assertSame([2], $this->ids($repository, '80/2'));
        self::assertSame([1], $this->ids($repository, '80'));
    }

    #[Test]
    public function it_restricts_the_search_to_building_plots_after_st(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 72, null),
            self::line(2, self::JICIN, 1, 72, null),
        ]);

        self::assertSame([2], $this->ids($repository, 'st. 72'));
    }

    #[Test]
    public function it_lists_land_before_building_at_the_same_distance(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 1, 72, null),
            self::line(2, self::JICIN, 2, 72, null),
        ]);

        self::assertSame([2, 1], $this->ids($repository, '72'));
        self::assertSame([2, 1], $this->ids($repository, '72', new Coordinates(15.35, 50.43)));
    }

    #[Test]
    public function it_lists_the_nearest_parcel_first_with_near(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 5, null, 15.35, 50.43),
            self::line(2, self::ROBOUSY, 2, 5, null, 15.40, 50.43),
            self::line(3, self::BECHARY, 2, 5, null, 15.45, 50.43),
        ]);

        self::assertSame([2, 1, 3], $this->ids($repository, '5', new Coordinates(15.39, 50.43)));
        self::assertSame([3, 2, 1], $this->ids($repository, '5', new Coordinates(15.50, 50.43)));
    }

    #[Test]
    public function it_orders_by_area_name_without_near_at_the_same_kind(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::ROBOUSY, 2, 5, null, 15.35, 50.43),
            self::line(2, self::JICIN, 2, 5, null, 15.45, 50.43),
            self::line(3, self::BECHARY, 2, 5, null, 15.40, 50.43),
        ]);

        self::assertSame([3, 2, 1], $this->ids($repository, '5'));
    }

    #[Test]
    public function it_scales_the_longitude_difference_by_the_cosine_of_the_latitude(): void
    {
        // At latitude 60 one degree of longitude is half as long as one degree of latitude, so the parcel 0.4 degrees
        // away in longitude (0.2 in latitude terms) is nearer than the one 0.3 degrees away in latitude.
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 5, null, 15.0, 60.3),
            self::line(2, self::ROBOUSY, 2, 5, null, 15.4, 60.0),
        ]);

        self::assertSame([2, 1], $this->ids($repository, '5', new Coordinates(15.0, 60.0)));
    }

    #[Test]
    public function it_requires_every_name_term_to_start_a_word_of_the_area_or_municipality_name(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 867, 121),
            self::line(2, self::ROBOUSY, 2, 867, 121),
            self::line(3, self::BECHARY, 2, 867, 121),
        ]);

        self::assertSame([2], $this->ids($repository, '867/121 rob'));
        self::assertSame([], $this->ids($repository, '867/121 obou'));
        self::assertSame([1, 2], $this->ids($repository, '867/121 jic'));
        self::assertSame([2], $this->ids($repository, '867/121 jic rob'));
        self::assertSame([], $this->ids($repository, '867/121 rob bech'));
        self::assertSame([3], $this->ids($repository, '867/121 Běchary'));
    }

    #[Test]
    public function it_strips_like_wildcards_from_the_text_before_matching(): void
    {
        $repository = $this->repositoryWith([self::line(1, self::ROBOUSY, 2, 5, null)]);

        // "%" and "_" are separators for SearchText, so they never reach the LIKE pattern: "r%" is the ignored letter "r".
        self::assertSame([1], $this->ids($repository, '5 r%'));
        self::assertSame([], $this->ids($repository, '5 _obousy'));
        self::assertSame([], $this->ids($repository, '5 %obousy'));
    }

    #[Test]
    public function it_narrows_the_parcels_to_the_area_code(): void
    {
        $repository = $this->repositoryWith([
            self::line(1, self::JICIN, 2, 5, null),
            self::line(2, self::ROBOUSY, 2, 5, null),
        ]);

        self::assertSame([2], $this->ids($repository, '5 740225'));
        self::assertSame([], $this->ids($repository, '5 100000'));
    }

    #[Test]
    public function it_returns_at_most_the_limit_of_parcels(): void
    {
        $lines = [];
        foreach (range(1, 12) as $id) {
            $lines[] = self::line($id, self::JICIN, 2, 5, null);
        }
        $repository = $this->repositoryWith($lines);

        self::assertCount(3, $repository->findParcels(new TilesetVersion(self::VERSION), SearchQuery::parse('5'), null, 3));
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8], $this->ids($repository, '5'));
    }

    #[Test]
    public function it_finds_nothing_for_a_query_without_a_parcel_number(): void
    {
        $repository = $this->repositoryWith([self::line(1, self::ROBOUSY, 2, 5, null)]);

        self::assertSame([], $repository->findParcels(new TilesetVersion(self::VERSION), SearchQuery::parse('robousy'), null, 8));
    }

    #[Test]
    public function it_returns_the_parcel_with_its_number_and_area(): void
    {
        $repository = $this->repositoryWith([self::line(99225194010, self::ROBOUSY, 2, 867, 121)]);

        $matches = $repository->findParcels(new TilesetVersion(self::VERSION), SearchQuery::parse('867/121'), null, 8);

        self::assertCount(1, $matches);
        self::assertSame(99225194010, $matches[0]->id->value);
        self::assertSame('867/121', $matches[0]->number->toString());
        self::assertSame(ParcelKind::Land, $matches[0]->number->kind);
        self::assertSame(self::ROBOUSY, $matches[0]->cadastralArea->code->value);
        self::assertSame('Robousy', $matches[0]->cadastralArea->name);
        self::assertSame(572659, $matches[0]->cadastralArea->municipality->code->value);
        self::assertSame('Jičín', $matches[0]->cadastralArea->municipality->name);
    }

    #[Test]
    public function it_tells_whether_an_index_of_the_version_is_published(): void
    {
        $repository = $this->repositoryWith([self::line(1, self::JICIN, 2, 5, null)]);

        self::assertTrue($repository->isPublished(new TilesetVersion(self::VERSION)));
        self::assertFalse($repository->isPublished(new TilesetVersion('19990101000000')));
    }

    #[Test]
    public function it_reports_an_unpublished_version(): void
    {
        $repository = $this->repositoryWith([self::line(1, self::JICIN, 2, 5, null)]);

        $this->expectException(SearchIndexNotPublished::class);

        $repository->findParcels(new TilesetVersion('19990101000000'), SearchQuery::parse('5'), null, 8);
    }

    #[Test]
    public function it_reports_a_file_that_is_not_an_sqlite_database(): void
    {
        mkdir($this->work->path . '/search');
        file_put_contents($this->work->path . '/search/' . self::VERSION . '.sqlite', 'this is not sqlite, but long enough to be looked at as a database header');

        $this->expectException(SearchIndexUnreadable::class);

        new SearchIndexRepositoryUsingSqlite($this->work->path . '/search')
            ->findParcels(new TilesetVersion(self::VERSION), SearchQuery::parse('5'), null, 8);
    }

    #[Test]
    public function it_reports_an_index_without_the_expected_tables(): void
    {
        mkdir($this->work->path . '/search');
        new \PDO('sqlite:' . $this->work->path . '/search/' . self::VERSION . '.sqlite')->exec('CREATE TABLE other (x INTEGER)');

        $this->expectException(SearchIndexUnreadable::class);

        new SearchIndexRepositoryUsingSqlite($this->work->path . '/search')
            ->findParcels(new TilesetVersion(self::VERSION), SearchQuery::parse('5'), null, 8);
    }

    #[Test]
    public function it_finds_places_by_word_prefixes_across_the_name_and_the_context(): void
    {
        $repository = $this->repositoryWithPlaces();

        self::assertSame(['Husova 1', 'Husova 127'], $this->placeNames($repository, 'husova 1'));
        self::assertSame(['Husova 2'], $this->placeNames($repository, 'husova 2'));
        self::assertSame(['Husova', 'Husova 1', 'Husova 127', 'Husova 2'], $this->placeNames($repository, 'husova jic'));
        self::assertSame([], $this->placeNames($repository, 'husova 3'));
        self::assertSame([], $this->placeNames($repository, 'usova'));
    }

    #[Test]
    public function it_ignores_case_and_diacritics_in_the_place_query(): void
    {
        $repository = $this->repositoryWithPlaces();

        self::assertSame(['Valdštejnovo náměstí', 'Valdštejnovo náměstí 4'], $this->placeNames($repository, 'valdstejnovo'));
        self::assertSame(['Valdštejnovo náměstí', 'Valdštejnovo náměstí 4'], $this->placeNames($repository, 'VALDŠTEJNOVO NÁM'));
    }

    #[Test]
    public function it_ranks_the_exact_key_before_a_key_prefix_before_the_kind(): void
    {
        $repository = $this->repositoryWithPlaces();

        // The municipality is the exact key, the street and the address start with it, the rest only has it in the
        // context or in a later word: the cadastral area before the part before the streets.
        self::assertSame(
            ['Jičín', 'Jičínská', 'Jičínská 1', 'Robousy', 'Staré Jičín', 'Dlouhá', 'Husova', 'Valdštejnovo náměstí'],
            $this->placeNames($repository, 'jicin'),
        );
    }

    #[Test]
    public function it_lists_the_nearest_place_first_among_equal_matches_with_near(): void
    {
        $repository = $this->repositoryWithPlaces();

        self::assertSame([572675, 572659], $this->municipalitiesOfStreets($repository, 'Dlouhá', new Coordinates(15.39, 50.0)));
        self::assertSame([572659, 572675], $this->municipalitiesOfStreets($repository, 'Dlouhá', new Coordinates(15.01, 50.0)));
        self::assertSame([572659, 572675], $this->municipalitiesOfStreets($repository, 'Dlouhá', null));
    }

    #[Test]
    public function it_returns_at_most_the_limit_of_places(): void
    {
        $repository = $this->repositoryWithPlaces();

        self::assertCount(3, $repository->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse('jicin'), null, 3));
        self::assertSame(['Jičín', 'Jičínská', 'Jičínská 1'], array_map(
            static fn($place): string => $place->name,
            $repository->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse('jicin'), null, 3),
        ));
    }

    #[Test]
    public function it_finds_no_places_when_the_query_searches_none(): void
    {
        $repository = $this->repositoryWithPlaces();

        self::assertSame([], $this->placeNames($repository, 'st. 72'));
        self::assertSame([], $this->placeNames($repository, '80/2'));
        self::assertSame([], $this->placeNames($repository, 'a'));
    }

    #[Test]
    public function it_returns_a_street_with_its_bounds_and_an_address_with_its_parcel(): void
    {
        $repository = $this->repositoryWithPlaces();

        $street = $repository->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse('Jičínská'), null, 8)[0] ?? null;
        $address = $repository->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse('Husova 127'), null, 8)[0] ?? null;

        self::assertNotNull($street);
        self::assertSame(PlaceKind::Street, $street->kind);
        self::assertSame(10, $street->code);
        self::assertSame(572659, $street->municipality->value);
        self::assertSame('Jičín', $street->context);
        self::assertEquals(new Coordinates(15.015, 50.015), $street->center);
        self::assertNotNull($street->bounds);
        self::assertEquals(new Coordinates(15.01, 50.01), $street->bounds->southWest);
        self::assertEquals(new Coordinates(15.02, 50.02), $street->bounds->northEast);
        self::assertNull($street->parcel);
        self::assertNotNull($address);
        self::assertSame(PlaceKind::Address, $address->kind);
        self::assertSame(1001, $address->code);
        self::assertSame('Husova 127', $address->name);
        self::assertSame('Staré Jičín, Jičín', $address->context);
        self::assertEquals(new Coordinates(15.0121, 50.0121), $address->center);
        self::assertNull($address->bounds);
        self::assertSame(1, $address->parcel?->value);
    }

    #[Test]
    public function it_reports_an_unpublished_version_for_a_place_search(): void
    {
        $repository = $this->repositoryWithPlaces();

        $this->expectException(SearchIndexNotPublished::class);

        $repository->findPlaces(new TilesetVersion('19990101000000'), SearchQuery::parse('jicin'), null, 8);
    }

    #[Test]
    public function it_reports_an_index_without_places_as_unreadable_for_a_place_search(): void
    {
        mkdir($this->work->path . '/search');
        new \PDO('sqlite:' . $this->work->path . '/search/' . self::VERSION . '.sqlite')->exec('CREATE TABLE other (x INTEGER)');

        $this->expectException(SearchIndexUnreadable::class);

        new SearchIndexRepositoryUsingSqlite($this->work->path . '/search')
            ->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse('jicin'), null, 8);
    }

    /**
     * @return list<string>
     */
    private function placeNames(SearchIndexRepositoryUsingSqlite $repository, string $text, ?Coordinates $near = null): array
    {
        return array_map(
            static fn($place): string => $place->name,
            $repository->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse($text), $near, 8),
        );
    }

    /**
     * @return list<int> the municipalities of the streets found, in the order found
     */
    private function municipalitiesOfStreets(SearchIndexRepositoryUsingSqlite $repository, string $text, ?Coordinates $near): array
    {
        $places = $repository->findPlaces(new TilesetVersion(self::VERSION), SearchQuery::parse($text), $near, 8);

        return array_values(array_map(
            static fn($place): int => $place->municipality->value,
            array_filter($places, static fn($place): bool => $place->kind === PlaceKind::Street),
        ));
    }

    /**
     * The index of two municipalities with parts, streets and addresses made for the ranking tests.
     */
    private function repositoryWithPlaces(): SearchIndexRepositoryUsingSqlite
    {
        $parcels = $this->work->path . '/place-parcels.geojsonl';
        file_put_contents($parcels, implode("\n", [
            self::line(1, self::JICIN, 2, 5, null),
            self::line(2, self::BECHARY, 2, 5, null),
            self::line(3, self::ROBOUSY, 2, 5, null, 15.1, 50.1),
        ]) . "\n");
        $jicin = $this->work->path . '/jicin-places.sqlite';
        PlaceFile::write(
            $jicin,
            572659,
            parts: [['code' => 1, 'name' => 'Staré Jičín', 'point' => '15.05 50.05'], ['code' => 2, 'name' => 'Centrum', 'point' => '15.06 50.06']],
            streets: [
                ['code' => 10, 'name' => 'Jičínská', 'line' => '15.01 50.01,15.02 50.02'],
                ['code' => 11, 'name' => 'Husova', 'line' => '15.03 50.03,15.04 50.04'],
                ['code' => 12, 'name' => 'Dlouhá', 'line' => '15.0 50.0,15.02 50.0'],
                ['code' => 13, 'name' => 'Valdštejnovo náměstí', 'line' => '15.07 50.07,15.08 50.08'],
            ],
            buildings: [['code' => 100, 'type' => 1, 'part' => 1, 'parcel' => 1]],
            addresses: [
                ['code' => 1000, 'number' => 1, 'building' => 100, 'street' => 10, 'point' => '15.011 50.011'],
                ['code' => 1001, 'number' => 127, 'building' => 100, 'street' => 11, 'point' => '15.0121 50.0121'],
                ['code' => 1002, 'number' => 1, 'building' => 100, 'street' => 11, 'point' => '15.0122 50.0122'],
                ['code' => 1003, 'number' => 2, 'building' => 100, 'street' => 11, 'point' => '15.0123 50.0123'],
                ['code' => 1004, 'number' => 3, 'building' => 100, 'street' => 12, 'point' => '15.0124 50.0124'],
                ['code' => 1005, 'number' => 4, 'building' => 100, 'street' => 13, 'point' => '15.0125 50.0125'],
            ],
        );
        $bechary = $this->work->path . '/bechary-places.sqlite';
        PlaceFile::write(
            $bechary,
            572675,
            parts: [['code' => 3, 'name' => 'Dolní', 'point' => '15.4 50.05']],
            streets: [['code' => 20, 'name' => 'Dlouhá', 'line' => '15.4 50.0,15.42 50.0']],
            buildings: [['code' => 200, 'type' => 1, 'part' => 3, 'parcel' => 2]],
            addresses: [['code' => 2000, 'number' => 9, 'building' => 200, 'street' => 20, 'point' => '15.41 50.0']],
        );

        $built = new SearchIndexBuilderUsingSqlite($this->work->path . '/build')->build(
            [new ConvertedParcels(new MunicipalityCode(572659), $parcels, [new AreaParcelCount(new CadastralAreaCode(self::JICIN), 1)])],
            [new ConvertedPlaces(new MunicipalityCode(572659), $jicin), new ConvertedPlaces(new MunicipalityCode(572675), $bechary)],
            self::coverage(),
        );
        $directory = $this->work->path . '/search';
        mkdir($directory);
        rename($built->path, $directory . '/' . self::VERSION . '.sqlite');

        return new SearchIndexRepositoryUsingSqlite($directory);
    }

    /**
     * @return list<int>
     */
    private function ids(SearchIndexRepositoryUsingSqlite $repository, string $text, ?Coordinates $near = null): array
    {
        $matches = $repository->findParcels(new TilesetVersion(self::VERSION), SearchQuery::parse($text), $near, 8);

        return array_map(static fn($match): int => $match->id->value, $matches);
    }

    /**
     * @param list<string> $lines
     */
    private function repositoryWith(array $lines): SearchIndexRepositoryUsingSqlite
    {
        $file = $this->work->path . '/input.geojsonl';
        file_put_contents($file, implode("\n", $lines) . "\n");
        $emptyPlaces = $this->work->path . '/no-places.sqlite';
        PlaceFile::write($emptyPlaces, 572659);

        return $this->publish($file, [new ConvertedPlaces(new MunicipalityCode(572659), $emptyPlaces)]);
    }

    /**
     * @param non-empty-list<ConvertedPlaces> $places
     */
    private function publish(string $parcelFile, array $places): SearchIndexRepositoryUsingSqlite
    {
        $built = new SearchIndexBuilderUsingSqlite($this->work->path . '/build')->build(
            [new ConvertedParcels(new MunicipalityCode(572659), $parcelFile, [new AreaParcelCount(new CadastralAreaCode(self::JICIN), 1)])],
            $places,
            self::coverage(),
        );
        $directory = $this->work->path . '/search';
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        rename($built->path, $directory . '/' . self::VERSION . '.sqlite');

        return new SearchIndexRepositoryUsingSqlite($directory);
    }

    private static function coverage(): Coverage
    {
        $jicin = new Municipality(new MunicipalityCode(572659), 'Jičín');

        return new Coverage(
            new CadastralArea(new CadastralAreaCode(self::JICIN), 'Jičín', $jicin),
            new CadastralArea(new CadastralAreaCode(self::ROBOUSY), 'Robousy', $jicin),
            new CadastralArea(new CadastralAreaCode(self::BECHARY), 'Běchary', new Municipality(new MunicipalityCode(572675), 'Běchary')),
        );
    }

    private static function line(int $id, int $areaCode, int $kind, int $base, ?int $subdivision, float $longitude = 15.35, float $latitude = 50.43): string
    {
        $properties = ['id' => $id, 'landType' => 14, 'areaCode' => $areaCode, 'kind' => $kind, 'baseNumber' => $base, 'area' => 100];
        if ($subdivision !== null) {
            $properties['subdivision'] = $subdivision;
        }
        $ring = [
            [$longitude - 0.0005, $latitude - 0.0005],
            [$longitude + 0.0005, $latitude - 0.0005],
            [$longitude + 0.0005, $latitude + 0.0005],
            [$longitude - 0.0005, $latitude - 0.0005],
        ];

        return json_encode(
            [
                'type' => 'Feature',
                'properties' => $properties,
                'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
            ],
            JSON_THROW_ON_ERROR,
        );
    }
}
