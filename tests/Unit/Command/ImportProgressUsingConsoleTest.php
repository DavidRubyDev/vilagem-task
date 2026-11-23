<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Command;

use ParcelMap\Command\ImportProgressUsingConsole;
use ParcelMap\Tests\Unit\Command\Console\MemoryConsoleOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportProgressUsingConsoleTest extends TestCase
{
    private MemoryConsoleOutput $output;
    private ImportProgressUsingConsole $progress;

    protected function setUp(): void
    {
        $this->output = new MemoryConsoleOutput();
        $this->progress = new ImportProgressUsingConsole($this->output);
    }

    #[Test]
    public function it_announces_the_import_with_plural_counts(): void
    {
        $this->progress->importStarted(240, 111);

        self::assertSame(['Importing 240 cadastral areas of 111 municipalities.'], $this->output->stdout);
    }

    #[Test]
    public function it_announces_the_import_with_singular_counts(): void
    {
        $this->progress->importStarted(1, 1);

        self::assertSame(['Importing 1 cadastral area of 1 municipality.'], $this->output->stdout);
    }

    #[Test]
    public function it_reports_the_fallback_to_the_previous_month(): void
    {
        $this->progress->exportDateFellBack('2026-11-30', '2026-10-31');

        self::assertSame(
            ['The RÚIAN export of 2026-11-30 is not published yet; using the export of 2026-10-31.'],
            $this->output->stdout,
        );
    }

    #[Test]
    public function it_reports_downloads_with_a_padded_position(): void
    {
        $this->progress->exportDownloaded(572667, 1, 111, false);
        $this->progress->exportDownloaded(572659, 12, 111, true);
        $this->progress->exportDownloaded(573850, 111, 111, false);

        self::assertSame(
            [
                'Download   1/111: municipality 572667 (downloaded)',
                'Download  12/111: municipality 572659 (cached)',
                'Download 111/111: municipality 573850 (downloaded)',
            ],
            $this->output->stdout,
        );
    }

    #[Test]
    public function it_reports_conversions_with_singular_and_plural_areas(): void
    {
        $this->progress->municipalityConverted(572667, 1, 111, 1, 1465);
        $this->progress->municipalityConverted(572659, 111, 111, 5, 16972);

        self::assertSame(
            [
                'Convert    1/111: municipality 572667, 1 cadastral area, 1465 parcels',
                'Convert  111/111: municipality 572659, 5 cadastral areas, 16972 parcels',
            ],
            $this->output->stdout,
        );
    }

    #[Test]
    public function it_announces_the_tileset_build(): void
    {
        $this->progress->tilesetBuildStarted(272011);

        self::assertSame(['Building the tileset from 272011 parcels.'], $this->output->stdout);
    }

    #[Test]
    public function it_announces_the_parcel_store_build(): void
    {
        $this->progress->parcelStoreBuildStarted(25);

        self::assertSame(['Building the parcel store from 25 parcels.'], $this->output->stdout);
    }
}
