<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\CodeList;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\ParcelStoreBuildFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Import\ParcelStoreBuilderUsingSqlite;
use ParcelMap\Tests\Support\SqliteFile;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelStoreBuilderUsingSqliteTest extends TestCase
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
    public function it_stores_every_parcel_and_every_covered_area_of_the_fixture(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

        self::assertSame($this->work->path . '/build/parcels.sqlite', $built->path);
        self::assertSame([], $built->unknownCodes);
        self::assertSame(25, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM parcels'));
        self::assertSame(0, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM metadata'));
        self::assertSame(
            [[659541, 'Jičín', 572659, 'Jičín'], [740225, 'Robousy', 572659, 'Jičín']],
            SqliteFile::rows($built->path, 'SELECT code, name, municipality_code, municipality_name FROM cadastral_areas ORDER BY code'),
        );
    }

    #[Test]
    public function it_stores_the_attributes_of_a_building_parcel_without_subdivision_or_land_use(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

        $row = SqliteFile::row($built->path, 'SELECT * FROM parcels WHERE id = 1753295604');

        self::assertIsArray($row);
        self::assertSame(659541, $row['cadastral_area_code']);
        self::assertSame(1, $row['kind']);
        self::assertSame(72, $row['base_number']);
        self::assertNull($row['subdivision']);
        self::assertSame(13, $row['land_type']);
        self::assertNull($row['land_use']);
        self::assertSame(455, $row['area']);
        $rings = $this->rings($row['boundary']);
        self::assertCount(1, $rings);
        self::assertCount(32, $rings[0]);
        self::assertSame([15.3509779, 50.4377444], $rings[0][0]);
    }

    #[Test]
    public function it_stores_the_holes_of_a_parcel_as_further_rings(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

        $row = SqliteFile::row($built->path, 'SELECT subdivision, land_use, boundary FROM parcels WHERE id = 1761441604');

        self::assertIsArray($row);
        self::assertNull($row['subdivision']);
        self::assertSame(17, $row['land_use']);
        self::assertCount(3, $this->rings($row['boundary']));
    }

    #[Test]
    public function it_stores_a_subdivision(): void
    {
        $built = $this->builder()->build([$this->converted(self::fixtureFile())], self::coverage());

        $row = SqliteFile::row($built->path, 'SELECT base_number, subdivision FROM parcels WHERE id = 99225194010');

        self::assertSame(['base_number' => 867, 'subdivision' => 121], $row);
    }

    #[Test]
    public function it_keeps_parcels_with_unknown_codes_and_counts_them_per_code(): void
    {
        $file = $this->file([
            self::line(1, landType: 99, landUse: 31),
            self::line(2, landType: 99, landUse: 31),
            self::line(3, landType: 14, landUse: 40),
            self::line(4, landType: 14, landUse: 26),
            self::line(5, landType: 14, landUse: null),
            self::line(6, landType: 98, landUse: 31),
        ]);

        $built = $this->builder()->build([$this->converted($file)], self::coverage());

        self::assertSame(6, SqliteFile::scalar($built->path, 'SELECT COUNT(*) FROM parcels'));
        self::assertSame(
            [
                [CodeList::LandType, 98, 1],
                [CodeList::LandType, 99, 2],
                [CodeList::LandUse, 31, 3],
                [CodeList::LandUse, 40, 1],
            ],
            array_map(static fn($code): array => [$code->list, $code->code, $code->parcelCount], $built->unknownCodes),
        );
    }

    #[Test]
    #[DataProvider('invalidLines')]
    public function it_fails_with_the_file_and_line_for_an_invalid_parcel_and_leaves_no_file(string $line, string $reason): void
    {
        $file = $this->file([self::line(1), $line]);

        try {
            $this->builder()->build([$this->converted($file)], self::coverage());
            self::fail('Expected ParcelStoreBuildFailed.');
        } catch (ParcelStoreBuildFailed $exception) {
            self::assertStringContainsString($file . ' line 2: ', $exception->getMessage(), $reason);
            self::assertFileDoesNotExist($this->work->path . '/build/parcels.sqlite');
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidLines(): array
    {
        return [
            'land type 0' => [self::line(2, landType: 0), 'land type'],
            'missing kind' => [self::line(2, kind: null), 'kind'],
            'unknown kind' => [self::line(2, kind: 3), 'kind'],
            'string area' => [str_replace('"area":100', '"area":"100"', self::line(2)), 'area'],
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
            self::fail('Expected ParcelStoreBuildFailed.');
        } catch (ParcelStoreBuildFailed) {
            self::assertFileDoesNotExist($this->work->path . '/build/parcels.sqlite');
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

    private function builder(): ParcelStoreBuilderUsingSqlite
    {
        return new ParcelStoreBuilderUsingSqlite($this->work->path . '/build');
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

    /**
     * @param mixed $blob a PDO column value, untyped until the assertion below has checked it
     * @return list<list<array{float, float}>>
     */
    private function rings(mixed $blob): array
    {
        self::assertIsString($blob);
        $json = gzuncompress($blob);
        self::assertIsString($json);
        $rings = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($rings);

        /** @var list<list<array{float, float}>> $rings */
        return $rings;
    }
}
