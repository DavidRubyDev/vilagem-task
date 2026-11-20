<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Coverage;

use ParcelMap\Application\Coverage\DownloadedStructure;
use ParcelMap\Application\Coverage\StructureReadFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\DistrictCode;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Infrastructure\Coverage\DistrictAreaExtractorUsingOgr2ogr;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DistrictAreaExtractorUsingOgr2ogrTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    #[Test]
    public function it_lists_the_areas_of_the_municipalities_of_the_district(): void
    {
        $areas = $this->extract(3604);

        self::assertEqualsCanonicalizing(
            [
                '601462 Běchary 572675',
                '659541 Jičín 572659',
                '725838 Popovice u Jičína 572659',
                '740217 Moravčice 572659',
                '740225 Robousy 572659',
                '771775 Hubálov 572659',
            ],
            array_map(
                static fn(CadastralArea $area): string => sprintf('%d %s %d', $area->code->value, $area->name, $area->municipality->value),
                $areas,
            ),
        );
    }

    #[Test]
    public function it_leaves_out_an_area_without_a_digital_map(): void
    {
        $areas = $this->extract(3407);

        $codes = array_map(static fn(CadastralArea $area): int => $area->code->value, $areas);
        sort($codes);
        self::assertSame([721506, 721514, 721522, 721531, 795682], $codes);
    }

    #[Test]
    public function it_returns_nothing_for_a_district_without_municipalities(): void
    {
        self::assertSame([], $this->extract(3605));
    }

    #[Test]
    public function it_reports_a_corrupt_zip_file(): void
    {
        $path = $this->directory->path . '/20261031_ST_UZSZ.xml.zip';
        file_put_contents($path, 'this is not a zip file');

        $this->expectException(StructureReadFailed::class);

        $this->extractor()->extract(new DownloadedStructure(ExportDate::fromString('2026-10-31'), $path, false), new DistrictCode(3604));
    }

    /**
     * @return list<CadastralArea>
     */
    private function extract(int $district): array
    {
        $structure = new DownloadedStructure(
            ExportDate::fromString('2026-10-31'),
            dirname(__DIR__, 3) . '/Fixtures/ruian/20261031_ST_UZSZ.xml.zip',
            true,
        );

        return $this->extractor()->extract($structure, new DistrictCode($district));
    }

    private function extractor(): DistrictAreaExtractorUsingOgr2ogr
    {
        return new DistrictAreaExtractorUsingOgr2ogr(new ProcessRunner());
    }
}
