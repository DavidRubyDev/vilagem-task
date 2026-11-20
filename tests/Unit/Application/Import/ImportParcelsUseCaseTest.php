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
use ParcelMap\Application\Import\TilesetPublishFailed;
use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Tests\Fake\ClockUsingFixedTime;
use ParcelMap\Tests\Fake\CoverageRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelConverterUsingMemory;
use ParcelMap\Tests\Fake\RuianExportDownloaderUsingMemory;
use ParcelMap\Tests\Fake\TilesetBuilderUsingMemory;
use ParcelMap\Tests\Fake\TilesetPublisherUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportParcelsUseCaseTest extends TestCase
{
    private RuianExportDownloaderUsingMemory $downloader;
    private ParcelConverterUsingMemory $converter;
    private TilesetBuilderUsingMemory $builder;
    private TilesetPublisherUsingMemory $publisher;

    #[Test]
    public function it_imports_all_covered_areas_and_publishes_the_tileset(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31']);

        $output = $useCase->execute(new ImportParcelsInput('2026-10-31'));

        self::assertSame(['572659 2026-10-31', '222222 2026-10-31'], $this->downloader->attempts);
        self::assertSame(
            [
                '659541 from memory://572659/2026-10-31',
                '740225 from memory://572659/2026-10-31',
                '111111 from memory://222222/2026-10-31',
            ],
            $this->converter->conversions,
        );
        self::assertSame(
            ['memory://parcels/659541', 'memory://parcels/740225', 'memory://parcels/111111'],
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

        $output = $useCase->execute(new ImportParcelsInput(null));

        self::assertSame(['572659 2026-10-31', '572659 2026-09-30', '222222 2026-09-30'], $this->downloader->attempts);
        self::assertSame('2026-09-30', $output->exportDate);
    }

    #[Test]
    public function it_uses_the_latest_export_without_a_fallback_when_it_is_published(): void
    {
        $output = $this->useCase(publishedDates: ['2026-10-31', '2026-09-30'])->execute(new ImportParcelsInput(null));

        self::assertSame('2026-10-31', $output->exportDate);
    }

    #[Test]
    public function it_fails_when_neither_of_the_last_two_exports_is_published(): void
    {
        $useCase = $this->useCase(publishedDates: []);

        $this->expectException(ExportNotPublished::class);

        try {
            $useCase->execute(new ImportParcelsInput(null));
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
            $useCase->execute(new ImportParcelsInput('2026-10-31'));
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
            $useCase->execute(new ImportParcelsInput('2026-10-30'));
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

        $useCase->execute(new ImportParcelsInput('2026-10-31'));
    }

    #[Test]
    public function it_stops_without_publishing_when_the_converter_fails(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], failingConverter: true);

        try {
            $useCase->execute(new ImportParcelsInput('2026-10-31'));
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
            $useCase->execute(new ImportParcelsInput('2026-10-31'));
            self::fail('Expected TileBuildFailed.');
        } catch (TileBuildFailed) {
            self::assertSame([], $this->publisher->published);
        }
    }

    #[Test]
    public function it_propagates_a_publisher_failure(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], failingPublisher: true);

        $this->expectException(TilesetPublishFailed::class);

        $useCase->execute(new ImportParcelsInput('2026-10-31'));
    }

    /**
     * @param list<string> $publishedDates
     */
    private function useCase(
        array $publishedDates,
        Coverage|CoverageUnavailable|null $coverage = null,
        bool $failingConverter = false,
        bool $failingBuilder = false,
        bool $failingPublisher = false,
    ): ImportParcelsUseCase {
        $this->downloader = new RuianExportDownloaderUsingMemory($publishedDates);
        $this->converter = new ParcelConverterUsingMemory([659541 => 22, 740225 => 3, 111111 => 5], $failingConverter);
        $this->builder = new TilesetBuilderUsingMemory($failingBuilder);
        $this->publisher = new TilesetPublisherUsingMemory($failingPublisher);

        return new ImportParcelsUseCase(
            new CoverageRepositoryUsingMemory($coverage ?? self::coverage()),
            $this->downloader,
            $this->converter,
            $this->builder,
            $this->publisher,
            new ClockUsingFixedTime(new DateTimeImmutable('2026-11-20 09:30:00', new DateTimeZone('UTC'))),
        );
    }

    private static function coverage(): Coverage
    {
        $municipality = new MunicipalityCode(572659);

        return new Coverage(
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', $municipality),
            new CadastralArea(new CadastralAreaCode(740225), 'Robousy', $municipality),
            new CadastralArea(new CadastralAreaCode(111111), 'Other', new MunicipalityCode(222222)),
        );
    }
}
