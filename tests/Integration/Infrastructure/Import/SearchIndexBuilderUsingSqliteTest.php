<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\SearchIndexBuildFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Import\SearchIndexBuilderUsingSqlite;
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
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

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
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

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
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

        self::assertSame(
            ['base_number' => 867, 'subdivision' => 121],
            SqliteFile::row($built->path, 'SELECT base_number, subdivision FROM parcels WHERE id = 99225194010'),
        );
    }

    #[Test]
    public function it_creates_the_index_that_finds_a_parcel_by_its_number(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

        $plan = SqliteFile::rows($built->path, 'EXPLAIN QUERY PLAN SELECT id FROM parcels WHERE base_number = 72 AND subdivision IS NULL');

        self::assertStringContainsString('parcels_by_number', (string) $plan[0][3]);
    }

    #[Test]
    #[DataProvider('invalidLines')]
    public function it_fails_with_the_file_and_line_for_an_invalid_parcel_and_leaves_no_file(string $line, string $reason): void
    {
        $file = $this->file([self::line(1), $line]);

        try {
            $this->builder()->build([$this->converted($file)], self::coverage());
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
            $this->builder()->build([$this->converted($this->work->path . '/missing.geojsonl')], self::coverage());
            self::fail('Expected SearchIndexBuildFailed.');
        } catch (SearchIndexBuildFailed) {
            self::assertFileDoesNotExist($this->work->path . '/build/search.sqlite');
        }
    }

    #[Test]
    public function it_replaces_the_file_of_an_earlier_build(): void
    {
        $builder = $this->builder();
        $builder->build([$this->converted($this->file([self::line(1), self::line(2)]))], self::coverage());

        $built = $builder->build([$this->converted($this->file([self::line(7)]))], self::coverage());

        self::assertSame(1, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM parcels'));
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
