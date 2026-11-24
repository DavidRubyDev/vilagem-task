<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\ConvertedPlaces;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\SearchIndexBuildFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Import\PlaceConverterUsingOgr2ogr;
use ParcelMap\Infrastructure\Import\SearchIndexBuilderUsingSqlite;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Tests\Support\PlaceFile;
use ParcelMap\Tests\Support\SqliteFile;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchIndexBuilderUsingSqliteTest extends TestCase
{
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
    public function it_indexes_every_parcel_and_every_covered_area_of_the_fixture(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->noPlaces(), self::coverage());

        self::assertSame($this->work->path . '/build/search.sqlite', $built->path);
        self::assertFileExists($built->path);
        self::assertSame(25, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM parcels'));
        self::assertSame(0, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM metadata'));
        self::assertSame(
            [
                [659541, 'Jičín', 572659, 'Jičín', 'jicin jicin'],
                [740225, 'Robousy', 572659, 'Jičín', 'robousy jicin'],
            ],
            SqliteFile::rows($built->path, 'SELECT code, name, municipality_code, municipality_name, search_words FROM cadastral_areas ORDER BY code'),
        );
    }

    #[Test]
    public function it_stores_the_number_and_the_outer_ring_bounds_of_a_parcel(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->noPlaces(), self::coverage());

        $row = SqliteFile::row($built->path, 'SELECT * FROM parcels WHERE id = 1753295604');

        self::assertSame(
            [
                'id' => 1753295604,
                'cadastral_area_code' => 659541,
                'kind' => 1,
                'base_number' => 72,
                'subdivision' => null,
                'west' => 15.3509216,
                'south' => 50.4372305,
                'east' => 15.3511404,
                'north' => 50.437745,
            ],
            $row,
        );
    }

    #[Test]
    public function it_stores_a_subdivision(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->noPlaces(), self::coverage());

        self::assertSame(
            ['base_number' => 867, 'subdivision' => 121],
            SqliteFile::row($built->path, 'SELECT base_number, subdivision FROM parcels WHERE id = 99225194010'),
        );
    }

    #[Test]
    public function it_creates_the_index_that_finds_a_parcel_by_its_number(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->noPlaces(), self::coverage());

        $plan = SqliteFile::rows($built->path, 'EXPLAIN QUERY PLAN SELECT id FROM parcels WHERE base_number = 72 AND subdivision IS NULL');

        self::assertStringContainsString('parcels_by_number', (string) $plan[0][3]);
    }

    #[Test]
    #[DataProvider('invalidLines')]
    public function it_fails_with_the_file_and_line_for_an_invalid_parcel_and_leaves_no_file(string $line, string $reason): void
    {
        $file = $this->file([self::line(1), $line]);

        try {
            $this->builder()->build([$this->converted($file)], $this->noPlaces(), self::coverage());
            self::fail('Expected SearchIndexBuildFailed.');
        } catch (SearchIndexBuildFailed $exception) {
            self::assertStringContainsString($file . ' line 2: ', $exception->getMessage(), $reason);
            self::assertFileDoesNotExist($this->work->path . '/build/search.sqlite');
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidLines(): array
    {
        return [
            'missing kind' => [self::line(2, kind: null), 'kind'],
            'unknown kind' => [self::line(2, kind: 3), 'kind'],
            'area code not covered' => [self::line(2, areaCode: 699999), 'area'],
            'duplicate id' => [self::line(1), 'duplicate'],
            'multipolygon' => [str_replace('"Polygon"', '"MultiPolygon"', self::line(2)), 'geometry'],
            'open ring' => [self::line(2, ring: '[[15.0, 50.0], [15.1, 50.0], [15.1, 50.1], [15.0, 50.2]]'), 'ring'],
            'not json' => ['this is not json', 'json'],
            'zero base number' => [self::line(2, baseNumber: 0), 'base number'],
        ];
    }

    #[Test]
    public function it_reports_a_missing_input_file_and_leaves_no_file(): void
    {
        try {
            $this->builder()->build([$this->converted($this->work->path . '/missing.geojsonl')], $this->noPlaces(), self::coverage());
            self::fail('Expected SearchIndexBuildFailed.');
        } catch (SearchIndexBuildFailed) {
            self::assertFileDoesNotExist($this->work->path . '/build/search.sqlite');
        }
    }

    #[Test]
    public function it_replaces_the_file_of_an_earlier_build(): void
    {
        $builder = $this->builder();
        $builder->build([$this->converted($this->file([self::line(1), self::line(2)]))], $this->noPlaces(), self::coverage());

        $built = $builder->build([$this->converted($this->file([self::line(7)]))], $this->noPlaces(), self::coverage());

        self::assertSame(1, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM parcels'));
    }

    #[Test]
    public function it_indexes_the_places_of_the_fixture(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        self::assertSame([1, 1, 1, 3, 21], [
            $built->places->municipalities,
            $built->places->cadastralAreas,
            $built->places->municipalityParts,
            $built->places->streets,
            $built->places->addresses,
        ]);
        self::assertSame(27, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM places'));
        self::assertSame(
            [
                [1, 572659, 'Jičín', ''],
                [2, 740225, 'Robousy', 'Jičín'],
                [3, 410217, 'Staré Město', 'Jičín'],
                [4, 164232, 'Čelakovského', 'Jičín'],
                [4, 164372, 'Valdštejnovo náměstí', 'Jičín'],
                [4, 164844, 'Palackého', 'Jičín'],
            ],
            SqliteFile::rows($built->path, 'SELECT kind, code, name, context FROM places WHERE kind < 5 ORDER BY kind, code'),
        );
    }

    #[Test]
    public function it_does_not_index_what_has_no_covered_address_or_has_the_name_of_a_bigger_place(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        // The cadastral area Jičín is the municipality Jičín, the part Robousy is the cadastral area Robousy, the
        // street 17. listopadu has no covered address and the address Robousy 1 stands on a parcel that is not covered.
        self::assertSame(0, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM places WHERE kind = 2 AND code = 659541'));
        self::assertSame(0, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM places WHERE kind = 3 AND code = 140228'));
        self::assertSame(0, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM places WHERE kind = 4 AND code = 164143'));
        self::assertSame(0, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM places WHERE kind = 5 AND code = 17482674'));
    }

    #[Test]
    public function it_gives_an_address_its_label_context_point_and_the_parcel_of_its_building(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        self::assertSame(
            [
                'kind' => 5,
                'code' => 25344951,
                'municipality_code' => 572659,
                'name' => 'Valdštejnovo náměstí 58',
                'context' => 'Staré Město, Jičín',
                'search_key' => 'valdstejnovo namesti 58',
                'center_longitude' => 15.351066,
                'center_latitude' => 50.4373768,
                'west' => null,
                'south' => null,
                'east' => null,
                'north' => null,
                'parcel_id' => 1753295604,
            ],
            SqliteFile::row(
                $built->path,
                'SELECT kind, code, municipality_code, name, context, search_key, center_longitude, center_latitude, west, south, east, north, parcel_id'
                . ' FROM places WHERE kind = 5 AND code = 25344951',
            ),
        );
        self::assertSame(
            ['Robousy 239', 'Jičín', 95522753010],
            SqliteFile::rows($built->path, 'SELECT name, context, parcel_id FROM places WHERE kind = 5 AND code = 85970085')[0] ?? null,
        );
    }

    #[Test]
    public function it_points_two_addresses_of_one_building_at_the_same_parcel(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        self::assertSame(
            [['Palackého 61', 1753298604], ['Valdštejnovo náměstí 61', 1753298604]],
            SqliteFile::rows($built->path, 'SELECT name, parcel_id FROM places WHERE code IN (17523320, 30292531) AND kind = 5 ORDER BY name'),
        );
    }

    #[Test]
    public function it_gives_the_municipality_and_the_cadastral_area_the_bounds_of_their_parcels(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        self::assertSame(
            SqliteFile::rows($built->path, 'SELECT MIN(west), MIN(south), MAX(east), MAX(north) FROM parcels'),
            SqliteFile::rows($built->path, 'SELECT west, south, east, north FROM places WHERE kind = 1'),
        );
        self::assertSame(
            SqliteFile::rows($built->path, 'SELECT MIN(west), MIN(south), MAX(east), MAX(north) FROM parcels WHERE cadastral_area_code = 740225'),
            SqliteFile::rows($built->path, 'SELECT west, south, east, north FROM places WHERE kind = 2'),
        );
    }

    #[Test]
    public function it_gives_a_street_and_a_part_bounds_and_the_centre_of_them(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        foreach ([[3, 410217], [4, 164372]] as [$kind, $code]) {
            $row = SqliteFile::row($built->path, sprintf('SELECT center_longitude, center_latitude, west, south, east, north FROM places WHERE kind = %d AND code = %d', $kind, $code));
            self::assertIsArray($row);
            self::assertLessThan($row['east'], $row['west']);
            self::assertLessThan($row['north'], $row['south']);
            self::assertEqualsWithDelta(((float) $row['west'] + (float) $row['east']) / 2, $row['center_longitude'], 1e-6);
            self::assertEqualsWithDelta(((float) $row['south'] + (float) $row['north']) / 2, $row['center_latitude'], 1e-6);
        }
        // The part is the rectangle of its indexed address points.
        $part = SqliteFile::row($built->path, 'SELECT west, east FROM places WHERE kind = 3');
        $points = SqliteFile::row($built->path, "SELECT MIN(center_longitude) AS west, MAX(center_longitude) AS east FROM places WHERE kind = 5 AND context LIKE 'Staré Město%'");
        self::assertEqualsWithDelta((float) ($points['west'] ?? 0), (float) ($part['west'] ?? 1), 1e-9);
        self::assertEqualsWithDelta((float) ($points['east'] ?? 0), (float) ($part['east'] ?? 1), 1e-9);
    }

    #[Test]
    public function it_writes_coordinates_rounded_to_seven_decimals(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        foreach (SqliteFile::rows($built->path, 'SELECT center_longitude, center_latitude, west, south, east, north FROM places') as $row) {
            foreach ($row as $value) {
                if ($value !== null) {
                    self::assertSame(round((float) $value, 7), (float) $value);
                }
            }
        }
    }

    #[Test]
    public function it_finds_places_by_word_prefixes_in_the_full_text_table(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], $this->fixturePlaces(), self::coverage());

        $names = SqliteFile::rows(
            $built->path,
            "SELECT p.name FROM places_fts f JOIN places p ON p.id = f.rowid WHERE places_fts MATCH '\"palackeho\"* AND \"61\"*' ORDER BY p.id",
        );
        self::assertSame([['Palackého 61']], $names);
        // The street and its eleven addresses.
        self::assertSame(
            12,
            SqliteFile::scalar($built->path, "SELECT COUNT(*) FROM places_fts WHERE places_fts MATCH '\"valdstejnovo\"*'"),
        );
    }

    #[Test]
    public function it_skips_addresses_that_cannot_be_found_and_what_hangs_on_them(): void
    {
        $places = $this->placeFile(
            parts: [
                ['code' => 1, 'name' => 'Used', 'point' => '15.05 50.05'],
                ['code' => 2, 'name' => 'Unused', 'point' => '15.06 50.06'],
            ],
            streets: [
                ['code' => 10, 'name' => 'Used street', 'line' => '15.01 50.01,15.02 50.02'],
                ['code' => 11, 'name' => 'Unused street', 'line' => '15.03 50.03,15.04 50.04'],
            ],
            buildings: [
                ['code' => 100, 'type' => 1, 'part' => 1, 'parcel' => 1],
                ['code' => 101, 'type' => 1, 'part' => 2, 'parcel' => 999],
            ],
            addresses: [
                ['code' => 1000, 'number' => 5, 'building' => 100, 'street' => 10, 'point' => '15.011 50.011'],
                ['code' => 1001, 'number' => 6, 'building' => 100, 'street' => 10, 'point' => null],
                ['code' => 1002, 'number' => 7, 'building' => 101, 'street' => 11, 'point' => '15.031 50.031'],
                ['code' => 1003, 'number' => 8, 'building' => 555, 'street' => 11, 'point' => '15.032 50.032'],
            ],
        );

        $built = $this->builder()->build([$this->converted($this->file([self::line(1), self::line(2)]))], $places, self::coverage());

        self::assertSame(
            [[3, 1, 'Used'], [4, 10, 'Used street'], [5, 1000, 'Used street 5']],
            SqliteFile::rows($built->path, 'SELECT kind, code, name FROM places WHERE kind > 2 ORDER BY kind, code'),
        );
    }

    #[Test]
    public function it_writes_orientation_numbers_letters_and_registration_numbers_the_czech_way(): void
    {
        $places = $this->placeFile(
            parts: [['code' => 1, 'name' => 'Part', 'point' => '15.05 50.05']],
            streets: [['code' => 10, 'name' => 'Husova', 'line' => '15.01 50.01,15.02 50.02']],
            buildings: [
                ['code' => 100, 'type' => 1, 'part' => 1, 'parcel' => 1],
                ['code' => 101, 'type' => 2, 'part' => 1, 'parcel' => 2],
            ],
            addresses: [
                ['code' => 1000, 'number' => 624, 'orientation' => 3, 'letter' => 'a', 'building' => 100, 'street' => 10, 'point' => '15.011 50.011'],
                ['code' => 1001, 'number' => 12, 'building' => 101, 'street' => 10, 'point' => '15.012 50.012'],
                ['code' => 1002, 'number' => 5, 'building' => 101, 'point' => '15.051 50.051'],
            ],
        );

        $built = $this->builder()->build([$this->converted($this->file([self::line(1), self::line(2)]))], $places, self::coverage());

        self::assertSame(
            [['Husova 624/3a', 'Part, Jičín'], ['Husova č. ev. 12', 'Part, Jičín'], ['Part č. ev. 5', 'Jičín']],
            SqliteFile::rows($built->path, 'SELECT name, context FROM places WHERE kind = 5 ORDER BY code'),
        );
    }

    #[Test]
    public function it_stores_a_place_with_a_single_point_without_bounds(): void
    {
        $places = $this->placeFile(
            parts: [['code' => 1, 'name' => 'Lonely part', 'point' => '15.05 50.05']],
            streets: [['code' => 10, 'name' => 'Vertical', 'line' => '15.01 50.01,15.01 50.02']],
            buildings: [['code' => 100, 'type' => 1, 'part' => 1, 'parcel' => 1]],
            addresses: [['code' => 1000, 'number' => 5, 'building' => 100, 'street' => 10, 'point' => '15.01234567891 50.01']],
        );

        $built = $this->builder()->build([$this->converted($this->file([self::line(1), self::line(2)]))], $places, self::coverage());

        self::assertSame(
            [
                [3, 'Lonely part', null, null, 15.0123457, 50.01],
                [4, 'Vertical', null, null, 15.01, 50.015],
            ],
            SqliteFile::rows($built->path, 'SELECT kind, name, west, north, center_longitude, center_latitude FROM places WHERE kind IN (3, 4) ORDER BY kind'),
        );
    }

    #[Test]
    public function it_keeps_a_street_name_that_occurs_in_two_municipalities_in_both(): void
    {
        $other = new Municipality(new MunicipalityCode(572675), 'Běchary');
        $coverage = new Coverage(
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín')),
            new CadastralArea(new CadastralAreaCode(601462), 'Běchary', $other),
        );
        $first = $this->placeFile(
            parts: [['code' => 1, 'name' => 'Part', 'point' => '15.05 50.05']],
            streets: [['code' => 10, 'name' => 'Husova', 'line' => '15.01 50.01,15.02 50.02']],
            buildings: [['code' => 100, 'type' => 1, 'part' => 1, 'parcel' => 1]],
            addresses: [['code' => 1000, 'number' => 5, 'building' => 100, 'street' => 10, 'point' => '15.011 50.011']],
            municipality: 572659,
        );
        $secondPath = $this->work->path . '/second-places.sqlite';
        PlaceFile::write(
            $secondPath,
            572675,
            parts: [['code' => 2, 'name' => 'Part', 'point' => '15.05 50.05']],
            streets: [['code' => 20, 'name' => 'Husova', 'line' => '15.01 50.01,15.02 50.02']],
            buildings: [['code' => 200, 'type' => 1, 'part' => 2, 'parcel' => 3]],
            addresses: [['code' => 2000, 'number' => 5, 'building' => 200, 'street' => 20, 'point' => '15.011 50.011']],
        );
        $parcels = $this->file([self::line(1), self::line(3, areaCode: 601462)]);
        $converted = new ConvertedParcels(new MunicipalityCode(572659), $parcels, [new AreaParcelCount(new CadastralAreaCode(659541), 1)]);

        $built = $this->builder()->build(
            [$converted],
            [...$first, new ConvertedPlaces(new MunicipalityCode(572675), $secondPath)],
            $coverage,
        );

        self::assertSame(
            [[572659, 'Part, Jičín'], [572675, 'Part, Běchary']],
            SqliteFile::rows($built->path, 'SELECT municipality_code, context FROM places WHERE kind = 5 ORDER BY municipality_code'),
        );
        self::assertSame(
            [[572659, 10], [572675, 20]],
            SqliteFile::rows($built->path, 'SELECT municipality_code, code FROM places WHERE kind = 4 ORDER BY municipality_code'),
        );
        self::assertSame([2, 0, 2, 2, 2], [
            $built->places->municipalities,
            $built->places->cadastralAreas,
            $built->places->municipalityParts,
            $built->places->streets,
            $built->places->addresses,
        ]);
    }

    /**
     * @param array{code: int, number: int, orientation?: int|null, letter?: string|null, point?: string|null} $address
     */
    #[Test]
    #[DataProvider('invalidPlaces')]
    public function it_fails_with_the_file_kind_and_code_for_an_invalid_place_and_leaves_no_file(array $address, string $reason): void
    {
        $path = $this->work->path . '/bad-places.sqlite';
        PlaceFile::write(
            $path,
            572659,
            parts: [['code' => 1, 'name' => 'Part', 'point' => '15.05 50.05']],
            buildings: [['code' => 100, 'type' => 1, 'part' => 1, 'parcel' => 1]],
            addresses: [$address + ['building' => 100]],
        );

        try {
            $this->builder()->build([$this->converted($this->file([self::line(1), self::line(2)]))], [new ConvertedPlaces(new MunicipalityCode(572659), $path)], self::coverage());
            self::fail('Expected SearchIndexBuildFailed.');
        } catch (SearchIndexBuildFailed $exception) {
            self::assertStringContainsString($path . ': address 1000: ', $exception->getMessage(), $reason);
            self::assertFileDoesNotExist($this->work->path . '/build/search.sqlite');
        }
    }

    /**
     * @return array<string, array{array{code: int, number: int, orientation?: int|null, letter?: string|null, point?: string|null}, string}>
     */
    public static function invalidPlaces(): array
    {
        return [
            'house number zero' => [['code' => 1000, 'number' => 0, 'point' => '15.01 50.01'], 'number'],
            'orientation number zero' => [['code' => 1000, 'number' => 5, 'orientation' => 0, 'point' => '15.01 50.01'], 'orientation'],
            'two letters' => [['code' => 1000, 'number' => 5, 'orientation' => 1, 'letter' => 'ab', 'point' => '15.01 50.01'], 'letter'],
            'a point that is not well-known text' => [['code' => 1000, 'number' => 5, 'point' => 'not a point'], 'point'],
            'a longitude out of range' => [['code' => 1000, 'number' => 5, 'point' => '200.0 50.0'], 'longitude'],
        ];
    }

    #[Test]
    public function it_fails_for_a_place_file_that_cannot_be_read(): void
    {
        try {
            $this->builder()->build(
                [$this->converted($this->file([self::line(1), self::line(2)]))],
                [new ConvertedPlaces(new MunicipalityCode(572659), $this->work->path . '/missing-places.sqlite')],
                self::coverage(),
            );
            self::fail('Expected SearchIndexBuildFailed.');
        } catch (SearchIndexBuildFailed) {
            self::assertFileDoesNotExist($this->work->path . '/build/search.sqlite');
        }
    }

    /**
     * @return non-empty-list<ConvertedPlaces> the places of the fixture export, converted by GDAL
     */
    private function fixturePlaces(): array
    {
        $converted = new PlaceConverterUsingOgr2ogr(new ProcessRunner(), $this->work->path)->convert(
            new DownloadedExport(
                new MunicipalityCode(572659),
                ExportDate::fromString('2026-10-31'),
                dirname(__DIR__, 3) . '/Fixtures/ruian/20261031_OB_572659_UKSH.xml.zip',
                true,
            ),
        );

        return [$converted];
    }

    /**
     * @param list<array{code: int, name: string, point: string}> $parts
     * @param list<array{code: int, name: string, line: string}> $streets
     * @param list<array{code: int, type: int, part: int, parcel: int}> $buildings
     * @param list<array{code: int, number: int, orientation?: int|null, letter?: string|null, building: int, street?: int|null, point?: string|null}> $addresses
     * @return non-empty-list<ConvertedPlaces>
     */
    private function placeFile(array $parts, array $streets, array $buildings, array $addresses, int $municipality = 572659): array
    {
        $path = $this->work->path . '/places-' . bin2hex(random_bytes(3)) . '.sqlite';
        PlaceFile::write($path, $municipality, $parts, $streets, $buildings, $addresses);

        return [new ConvertedPlaces(new MunicipalityCode($municipality), $path)];
    }

    /**
     * @return non-empty-list<ConvertedPlaces> a place file of the fixture municipality without any places
     */
    private function noPlaces(): array
    {
        $path = $this->work->path . '/no-places.sqlite';
        if (!is_file($path)) {
            PlaceFile::write($path, 572659);
        }

        return [new ConvertedPlaces(new MunicipalityCode(572659), $path)];
    }

    private function builder(): SearchIndexBuilderUsingSqlite
    {
        return new SearchIndexBuilderUsingSqlite($this->work->path . '/build');
    }

    private function converted(string $file): ConvertedParcels
    {
        return new ConvertedParcels(new MunicipalityCode(572659), $file, [new AreaParcelCount(new CadastralAreaCode(659541), 1)]);
    }

    private static function fixtureFile(): string
    {
        return dirname(__DIR__, 3) . '/Fixtures/geojson/572659.geojsonl';
    }

    private static function coverage(): Coverage
    {
        $municipality = new Municipality(new MunicipalityCode(572659), 'Jičín');

        return new Coverage(
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', $municipality),
            new CadastralArea(new CadastralAreaCode(740225), 'Robousy', $municipality),
        );
    }

    /**
     * @param list<string> $lines
     */
    private function file(array $lines): string
    {
        $path = $this->work->path . '/input-' . bin2hex(random_bytes(3)) . '.geojsonl';
        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }

    private static function line(
        int $id,
        int $landType = 14,
        ?int $landUse = null,
        ?int $kind = 2,
        int $areaCode = 659541,
        int $baseNumber = 5,
        ?string $ring = null,
    ): string {
        $properties = ['id' => $id, 'landType' => $landType, 'areaCode' => $areaCode];
        if ($kind !== null) {
            $properties['kind'] = $kind;
        }
        $properties += ['baseNumber' => $baseNumber, 'area' => 100];
        if ($landUse !== null) {
            $properties['landUse'] = $landUse;
        }

        return sprintf(
            '{"type": "Feature", "properties": %s, "geometry": {"type": "Polygon", "coordinates": [%s]}}',
            json_encode($properties, JSON_THROW_ON_ERROR),
            $ring ?? '[[15.0, 50.0], [15.1, 50.0], [15.1, 50.1], [15.0, 50.0]]',
        );
    }
}
