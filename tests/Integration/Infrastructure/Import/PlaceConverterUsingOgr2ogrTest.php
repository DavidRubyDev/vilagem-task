<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Import\PlaceConverterUsingOgr2ogr;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Tests\Support\SqliteFile;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlaceConverterUsingOgr2ogrTest extends TestCase
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
    public function it_writes_the_four_place_layers_of_a_municipality_into_one_file(): void
    {
        $converted = $this->converter()->convert(self::export(572659, 'OB_572659'));

        self::assertSame(572659, $converted->municipality->value);
        self::assertSame($this->work->path . '/places/572659.sqlite', $converted->path);
        self::assertSame([$this->work->path . '/places/572659.sqlite'], glob($this->work->path . '/places/*'));
        self::assertSame(2, SqliteFile::scalar($converted->path, 'SELECT COUNT(*) FROM castiobci'));
        self::assertSame(4, SqliteFile::scalar($converted->path, 'SELECT COUNT(*) FROM ulice'));
        self::assertSame(20, SqliteFile::scalar($converted->path, 'SELECT COUNT(*) FROM stavebniobjekty'));
        self::assertSame(22, SqliteFile::scalar($converted->path, 'SELECT COUNT(*) FROM adresnimista'));
    }

    #[Test]
    public function it_converts_to_wgs84_as_well_known_text_in_longitude_latitude_order(): void
    {
        $converted = $this->converter()->convert(self::export(572659, 'OB_572659'));

        $address = SqliteFile::row(
            $converted->path,
            'SELECT cislodomovni, cisloorientacni, stavebniobjektkod, ulicekod, adresnibod FROM adresnimista WHERE kod = 25344951',
        );
        self::assertIsArray($address);
        self::assertSame(58, $address['cislodomovni']);
        self::assertNull($address['cisloorientacni']);
        self::assertSame(24875431, $address['stavebniobjektkod']);
        self::assertSame(164372, $address['ulicekod']);
        self::assertIsString($address['adresnibod']);
        self::assertMatchesRegularExpression('/^POINT \(15\.351\d+ 50\.437\d+\)$/', $address['adresnibod']);

        $street = SqliteFile::scalar($converted->path, 'SELECT definicnicara FROM ulice WHERE kod = 164372');
        self::assertIsString($street);
        self::assertStringStartsWith('MULTILINESTRING ((15.35', $street);
    }

    #[Test]
    public function it_keeps_the_columns_the_index_builder_joins_on(): void
    {
        $converted = $this->converter()->convert(self::export(572659, 'OB_572659'));

        $building = SqliteFile::row(
            $converted->path,
            'SELECT kod, typstavebnihoobjektukod, castobcekod, identifikacniparcelaid FROM stavebniobjekty WHERE kod = 24875431',
        );
        self::assertSame(
            ['kod' => 24875431, 'typstavebnihoobjektukod' => 1, 'castobcekod' => 410217, 'identifikacniparcelaid' => 1753295604],
            $building,
        );
        self::assertSame(
            ['kod' => 410217, 'nazev' => 'Staré Město', 'obeckod' => 572659],
            SqliteFile::row($converted->path, 'SELECT kod, nazev, obeckod FROM castiobci WHERE kod = 410217'),
        );
        self::assertSame(
            ['kod' => 164372, 'nazev' => 'Valdštejnovo náměstí', 'obeckod' => 572659],
            SqliteFile::row($converted->path, 'SELECT kod, nazev, obeckod FROM ulice WHERE kod = 164372'),
        );
    }

    #[Test]
    public function it_writes_empty_tables_for_an_export_without_places(): void
    {
        $converted = $this->converter()->convert(self::export(572675, 'OB_572675'));

        foreach (['castiobci', 'ulice', 'stavebniobjekty', 'adresnimista'] as $table) {
            self::assertSame(0, SqliteFile::scalar($converted->path, 'SELECT COUNT(*) FROM ' . $table), $table);
        }
    }

    #[Test]
    public function it_replaces_the_file_of_an_earlier_conversion(): void
    {
        $converter = $this->converter();
        $converter->convert(self::export(572659, 'OB_572659'));

        $converted = $converter->convert(self::export(572659, 'OB_572659'));

        self::assertSame(22, SqliteFile::scalar($converted->path, 'SELECT COUNT(*) FROM adresnimista'));
    }

    #[Test]
    public function it_reports_a_corrupt_zip_file_and_leaves_no_file(): void
    {
        $path = $this->work->path . '/20261031_OB_572659_UKSH.xml.zip';
        file_put_contents($path, 'this is not a zip file');
        $export = new DownloadedExport(new MunicipalityCode(572659), ExportDate::fromString('2026-10-31'), $path, false);

        try {
            $this->converter()->convert($export);
            self::fail('Expected ConversionFailed.');
        } catch (ConversionFailed $exception) {
            self::assertStringContainsString($path, $exception->getMessage());
            self::assertSame([], glob($this->work->path . '/places/*'));
        }
    }

    private function converter(): PlaceConverterUsingOgr2ogr
    {
        return new PlaceConverterUsingOgr2ogr(new ProcessRunner(), $this->work->path);
    }

    private static function export(int $municipality, string $name): DownloadedExport
    {
        return new DownloadedExport(
            new MunicipalityCode($municipality),
            ExportDate::fromString('2026-10-31'),
            dirname(__DIR__, 3) . '/Fixtures/ruian/20261031_' . $name . '_UKSH.xml.zip',
            true,
        );
    }
}
