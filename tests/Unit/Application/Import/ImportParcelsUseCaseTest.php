<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Import;

use DateTimeImmutable;
use DateTimeZone;
use ParcelMap\Application\Import\CoverageNotAvailable;
use ParcelMap\Application\Import\ExportNotPublished;
use ParcelMap\Application\Import\ImportParcelsInput;
use ParcelMap\Application\Import\ImportParcelsUseCase;
use ParcelMap\Application\Import\InvalidImportRequest;
use ParcelMap\Application\Import\TileBuildFailed;
use ParcelMap\Application\Import\TilesetInspectionFailed;
use ParcelMap\Application\Import\TilesetOverBudget;
use ParcelMap\Application\Import\TilesetPublishFailed;
use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\TilesetStatistics;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Domain\Map\ZoomTileStatistics;
use ParcelMap\Tests\Fake\ClockUsingFixedTime;
use ParcelMap\Tests\Fake\CoverageRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ImportProgressUsingMemory;
use ParcelMap\Tests\Fake\ParcelConverterUsingMemory;
use ParcelMap\Tests\Fake\RuianExportDownloaderUsingMemory;
use ParcelMap\Tests\Fake\TilesetBuilderUsingMemory;
use ParcelMap\Tests\Fake\TilesetInspectorUsingMemory;
use ParcelMap\Tests\Fake\TilesetPublisherUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportParcelsUseCaseTest extends TestCase
{
    private RuianExportDownloaderUsingMemory $downloader;
    private ParcelConverterUsingMemory $converter;
    private TilesetBuilderUsingMemory $builder;
    private TilesetPublisherUsingMemory $publisher;
    private ImportProgressUsingMemory $progress;

    #[Test]
    public function it_imports_all_covered_areas_and_publishes_the_tileset(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31']);

        $output = $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);

        self::assertSame(['572659 2026-10-31', '222222 2026-10-31'], $this->downloader->attempts);
        self::assertSame(
            [
                '572659: 659541,740225 from memory://572659/2026-10-31',
                '222222: 111111 from memory://222222/2026-10-31',
            ],
            $this->converter->conversions,
        );
        self::assertSame(
            ['memory://municipalities/572659', 'memory://municipalities/222222'],
            $this->builder->builtFrom,
        );
        self::assertSame(['memory://tileset 20261120093000 2026-10-31'], $this->publisher->published);
        self::assertSame('2026-10-31', $output->exportDate);
        self::assertSame('20261120093000', $output->tilesetVersion);
        self::assertSame(22 + 3 + 5, $output->totalParcels);
        self::assertSame([659541, 740225, 111111], array_map(static fn($a): int => $a->code, $output->areas));
        self::assertSame(['Jičín', 'Robousy', 'Other'], array_map(static fn($a): string => $a->name, $output->areas));
        self::assertSame([22, 3, 5], array_map(static fn($a): int => $a->parcelCount, $output->areas));
    }

    #[Test]
    public function it_falls_back_once_to_the_previous_month_when_the_latest_export_is_not_published(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-09-30']);

        $output = $useCase->execute(new ImportParcelsInput(null), $this->progress);

        self::assertSame(['572659 2026-10-31', '572659 2026-09-30', '222222 2026-09-30'], $this->downloader->attempts);
        self::assertSame('2026-09-30', $output->exportDate);
    }

    #[Test]
    public function it_uses_the_latest_export_without_a_fallback_when_it_is_published(): void
    {
        $output = $this->useCase(publishedDates: ['2026-10-31', '2026-09-30'])->execute(new ImportParcelsInput(null), $this->progress);

        self::assertSame('2026-10-31', $output->exportDate);
    }

    #[Test]
    public function it_fails_when_neither_of_the_last_two_exports_is_published(): void
    {
        $useCase = $this->useCase(publishedDates: []);

        $this->expectException(ExportNotPublished::class);

        try {
            $useCase->execute(new ImportParcelsInput(null), $this->progress);
        } finally {
            self::assertSame(['572659 2026-10-31', '572659 2026-09-30'], $this->downloader->attempts);
            self::assertSame([], $this->publisher->published);
        }
    }

    #[Test]
    public function it_does_not_fall_back_for_an_explicit_export_date(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-09-30']);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
            self::fail('Expected ExportNotPublished.');
        } catch (ExportNotPublished) {
            self::assertSame(['572659 2026-10-31'], $this->downloader->attempts);
        }
    }

    #[Test]
    public function it_rejects_an_invalid_export_date_before_downloading_anything(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31']);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-30'), $this->progress);
            self::fail('Expected InvalidImportRequest.');
        } catch (InvalidImportRequest) {
            self::assertSame([], $this->downloader->attempts);
        }
    }

    #[Test]
    public function it_reports_an_unavailable_coverage(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], coverage: CoverageUnavailable::because('no file'));

        $this->expectException(CoverageNotAvailable::class);

        $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
    }

    #[Test]
    public function it_stops_without_publishing_when_the_converter_fails(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], failingConverter: true);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
            self::fail('Expected ConversionFailed.');
        } catch (ConversionFailed) {
            self::assertSame([], $this->publisher->published);
        }
    }

    #[Test]
    public function it_stops_without_publishing_when_the_builder_fails(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], failingBuilder: true);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
            self::fail('Expected TileBuildFailed.');
        } catch (TileBuildFailed) {
            self::assertSame([], $this->publisher->published);
        }
    }

    #[Test]
    public function it_builds_with_the_injected_budget(): void
    {
        $this->useCase(publishedDates: ['2026-10-31'])->execute(new ImportParcelsInput('2026-10-31'), $this->progress);

        self::assertNotNull($this->builder->budget);
        self::assertSame(150_000, $this->builder->budget->maxBytes);
    }

    #[Test]
    public function it_reports_the_budget_and_the_tiles_per_zoom_in_ascending_order_when_within_budget(): void
    {
        $statistics = new TilesetStatistics(
            new ZoomTileStatistics(12, 41, 150_000, new TileCoordinate(12, 2222, 1381)),
            new ZoomTileStatistics(9, 4, 120_000, new TileCoordinate(9, 277, 172)),
        );

        $output = $this->useCase(publishedDates: ['2026-10-31'], statistics: $statistics)
            ->execute(new ImportParcelsInput('2026-10-31'), $this->progress);

        self::assertSame(150_000, $output->tileSizeBudgetBytes);
        self::assertSame([9, 12], array_map(static fn($zoom): int => $zoom->zoom, $output->zoomLevels));
        self::assertSame([4, 41], array_map(static fn($zoom): int => $zoom->tileCount, $output->zoomLevels));
        self::assertSame([120_000, 150_000], array_map(static fn($zoom): int => $zoom->largestTileBytes, $output->zoomLevels));
        self::assertCount(1, $this->publisher->published);
    }

    #[Test]
    public function it_publishes_nothing_when_a_tile_is_over_budget(): void
    {
        $statistics = new TilesetStatistics(
            new ZoomTileStatistics(9, 4, 120_000, new TileCoordinate(9, 277, 172)),
            new ZoomTileStatistics(11, 15, 150_001, new TileCoordinate(11, 1111, 690)),
        );
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], statistics: $statistics);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
            self::fail('Expected TilesetOverBudget.');
        } catch (TilesetOverBudget $exception) {
            self::assertSame(
                'Tile 11/1111/690 has 150001 bytes, over the budget of 150000 bytes; the tileset was not published.',
                $exception->getMessage(),
            );
            self::assertSame([], $this->publisher->published);
        }
    }

    #[Test]
    public function it_publishes_nothing_when_the_inspection_fails(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], statistics: TilesetInspectionFailed::because('unreadable'));

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
            self::fail('Expected TilesetInspectionFailed.');
        } catch (TilesetInspectionFailed) {
            self::assertSame([], $this->publisher->published);
        }
    }

    #[Test]
    public function it_propagates_a_publisher_failure(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], failingPublisher: true);

        $this->expectException(TilesetPublishFailed::class);

        $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
    }

    #[Test]
    public function it_reports_each_download_and_conversion_in_order_with_positions_and_cache_state(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], cachedMunicipalities: [222222]);

        $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);

        self::assertSame(
            [
                'started 3 areas, 2 municipalities',
                'downloaded 572659 1/2 downloaded',
                'downloaded 222222 2/2 cached',
                'converted 572659 1/2 2 areas, 25 parcels',
                'converted 222222 2/2 1 areas, 5 parcels',
                'build from 30 parcels',
            ],
            $this->progress->events,
        );
    }

    #[Test]
    public function it_reports_the_fallback_to_the_previous_month_only_when_it_happens(): void
    {
        $this->useCase(publishedDates: ['2026-09-30'])->execute(new ImportParcelsInput(null), $this->progress);

        self::assertSame(
            ['started 3 areas, 2 municipalities', 'fell back from 2026-10-31 to 2026-09-30'],
            array_slice($this->progress->events, 0, 2),
        );
        self::assertSame(['572659 2026-10-31', '572659 2026-09-30', '222222 2026-09-30'], $this->downloader->attempts);
    }

    #[Test]
    public function it_reports_no_fallback_when_the_latest_export_is_published(): void
    {
        $this->useCase(publishedDates: ['2026-10-31'])->execute(new ImportParcelsInput(null), $this->progress);

        self::assertSame(
            ['started 3 areas, 2 municipalities', 'downloaded 572659 1/2 downloaded', 'downloaded 222222 2/2 downloaded'],
            array_slice($this->progress->events, 0, 3),
        );
    }

    #[Test]
    public function it_reports_nothing_after_a_download_failure(): void
    {
        $useCase = $this->useCase(publishedDates: []);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'), $this->progress);
            self::fail('Expected ExportNotPublished.');
        } catch (ExportNotPublished) {
            self::assertSame(['started 3 areas, 2 municipalities'], $this->progress->events);
        }
    }

    /**
     * @param list<string> $publishedDates
     * @param list<int> $cachedMunicipalities
     */
    private function useCase(
        array $publishedDates,
        array $cachedMunicipalities = [],
        Coverage|CoverageUnavailable|null $coverage = null,
        bool $failingConverter = false,
        bool $failingBuilder = false,
        bool $failingPublisher = false,
        TilesetStatistics|TilesetInspectionFailed|null $statistics = null,
    ): ImportParcelsUseCase {
        $this->downloader = new RuianExportDownloaderUsingMemory($publishedDates, $cachedMunicipalities);
        $this->progress = new ImportProgressUsingMemory();
        $this->converter = new ParcelConverterUsingMemory([659541 => 22, 740225 => 3, 111111 => 5], $failingConverter);
        $this->builder = new TilesetBuilderUsingMemory($failingBuilder);
        $this->publisher = new TilesetPublisherUsingMemory($failingPublisher);

        return new ImportParcelsUseCase(
            new CoverageRepositoryUsingMemory($coverage ?? self::coverage()),
            $this->downloader,
            $this->converter,
            $this->builder,
            new TilesetInspectorUsingMemory($statistics ?? self::withinBudget()),
            $this->publisher,
            new ClockUsingFixedTime(new DateTimeImmutable('2026-11-20 09:30:00', new DateTimeZone('UTC'))),
            new TileSizeBudget(150_000),
        );
    }

    private static function withinBudget(): TilesetStatistics
    {
        return new TilesetStatistics(new ZoomTileStatistics(9, 4, 100_000, new TileCoordinate(9, 277, 172)));
    }

    private static function coverage(): Coverage
    {
        $municipality = new Municipality(new MunicipalityCode(572659), 'Jičín');

        return new Coverage(
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', $municipality),
            new CadastralArea(new CadastralAreaCode(740225), 'Robousy', $municipality),
            new CadastralArea(new CadastralAreaCode(111111), 'Other', new Municipality(new MunicipalityCode(222222), 'Other')),
        );
    }
}
