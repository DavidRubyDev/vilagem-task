<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\CadastralAreaNotInExport;
use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Import\ParcelConverterUsingOgr2ogr;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelConverterUsingOgr2ogrTest extends TestCase
{
    private const array FIXTURE_IDS = [
        1753292604, 1753293604, 1753294604, 1753295604, 1753296604, 1753297604, 1753298604, 1753299604,
        1753300604, 1753301604, 1753303604, 1753304604, 1753305604, 1753306604, 1753309604, 1753310604,
        1753691604, 1753692604, 1761441604, 2654592604, 64343200010, 64343201010,
    ];

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
    public function it_converts_the_parcels_of_an_area_to_wgs84_geojson_lines(): void
    {
        $converted = $this->converter()->convert($this->fixtureExport(), self::area(659541, 'Jičín'));

        self::assertSame(22, $converted->parcelCount);
        self::assertSame(659541, $converted->area->value);
        self::assertSame($this->work->path . '/parcels/659541.geojsonl', $converted->path);

        $lines = file($converted->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(22, $lines);
        $ids = [];
        foreach ($lines as $line) {
            $feature = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($feature);
            self::assertIsArray($feature['properties']);
            self::assertSame(['id', 'landType'], array_keys($feature['properties']));
            $ids[] = $feature['properties']['id'];
            self::assertIsArray($feature['geometry']);
            self::assertIsArray($feature['geometry']['coordinates']);
            $this->assertInsideJicin($feature['geometry']['coordinates']);
        }
        sort($ids);
        $expected = self::FIXTURE_IDS;
        sort($expected);
        self::assertSame($expected, $ids);
    }

    #[Test]
    public function it_converts_only_the_requested_area(): void
    {
        $converted = $this->converter()->convert($this->fixtureExport(), self::area(740225, 'Robousy'));

        self::assertSame(3, $converted->parcelCount);
    }

    #[Test]
    public function it_reports_an_area_that_is_not_in_the_export_and_leaves_no_file(): void
    {
        try {
            $this->converter()->convert($this->fixtureExport(), self::area(771775, 'Hubálov'));
            self::fail('Expected CadastralAreaNotInExport.');
        } catch (CadastralAreaNotInExport) {
            self::assertSame([], glob($this->work->path . '/parcels/*'));
        }
    }

    #[Test]
    public function it_reports_a_corrupt_zip_file(): void
    {
        $path = $this->work->path . '/20261031_OB_572659_UKSH.xml.zip';
        file_put_contents($path, 'this is not a zip file');
        $export = new DownloadedExport(new MunicipalityCode(572659), ExportDate::fromString('2026-10-31'), $path);

        $this->expectException(ConversionFailed::class);

        $this->converter()->convert($export, self::area(659541, 'Jičín'));
    }

    private function converter(): ParcelConverterUsingOgr2ogr
    {
        return new ParcelConverterUsingOgr2ogr(new ProcessRunner(), $this->work->path);
    }

    private function fixtureExport(): DownloadedExport
    {
        return new DownloadedExport(
            new MunicipalityCode(572659),
            ExportDate::fromString('2026-10-31'),
            dirname(__DIR__, 3) . '/Fixtures/ruian/20261031_OB_572659_UKSH.xml.zip',
        );
    }

    private static function area(int $code, string $name): CadastralArea
    {
        return new CadastralArea(new CadastralAreaCode($code), $name, new MunicipalityCode(572659));
    }

    /**
     * @param array<mixed> $coordinates nested GeoJSON coordinate arrays
     */
    private function assertInsideJicin(array $coordinates): void
    {
        foreach ($coordinates as $item) {
            self::assertIsArray($item);
            if (is_array($item[0])) {
                $this->assertInsideJicin($item);

                continue;
            }
            self::assertGreaterThan(15.3, $item[0]);
            self::assertLessThan(15.5, $item[0]);
            self::assertGreaterThan(50.3, $item[1]);
            self::assertLessThan(50.5, $item[1]);
        }
    }
}
