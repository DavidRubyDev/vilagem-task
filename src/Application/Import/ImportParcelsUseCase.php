<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use LogicException;
use ParcelMap\Application\Clock\Clock;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageRepository;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\InvalidExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Map\TilesetStatistics;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Domain\Map\ZoomTileStatistics;

final readonly class ImportParcelsUseCase
{
    public function __construct(
        private CoverageRepository $coverage,
        private RuianExportDownloader $downloader,
        private ParcelConverter $converter,
        private TilesetBuilder $builder,
        private TilesetInspector $inspector,
        private ParcelStoreBuilder $storeBuilder,
        private SearchIndexBuilder $searchIndexBuilder,
        private ParcelDataPublisher $publisher,
        private Clock $clock,
        private TileSizeBudget $budget,
    ) {}

    /**
     * @throws InvalidImportRequest
     * @throws ImportFailed
     */
    public function execute(ImportParcelsInput $input, ImportProgress $progress): ImportParcelsOutput
    {
        $coverage = $this->coverage();
        $municipalities = $coverage->municipalities();
        $progress->importStarted(count($coverage->areas()), count($municipalities));
        $exports = $this->download(
            $municipalities,
            $this->exportDate($input),
            allowFallback: $input->exportDate === null,
            progress: $progress,
        );

        $converted = [];
        foreach ($municipalities as $index => $municipality) {
            $areas = $coverage->areasOf($municipality);
            $parcels = $this->converter->convert($exports[$municipality->value], $areas);
            $converted[] = $parcels;
            $progress->municipalityConverted(
                $municipality->value,
                $index + 1,
                count($municipalities),
                count($areas),
                $parcels->totalParcelCount(),
            );
        }

        $areas = array_map(
            fn(CadastralArea $area): ImportedAreaOutput => new ImportedAreaOutput(
                $area->code->value,
                $area->name,
                $this->parcelCount($converted, $area),
            ),
            $coverage->areas(),
        );

        $exportDate = array_first($exports)->date;
        $totalParcels = array_sum(array_map(static fn(ConvertedParcels $parcels): int => $parcels->totalParcelCount(), $converted));
        $progress->tilesetBuildStarted($totalParcels);
        $tileset = $this->builder->build($converted, $this->budget);
        $statistics = $this->inspector->inspect($tileset);
        $this->requireWithinBudget($statistics);
        $progress->parcelStoreBuildStarted($totalParcels);
        $store = $this->storeBuilder->build($converted, $coverage);
        $progress->searchIndexBuildStarted($totalParcels);
        $index = $this->searchIndexBuilder->build($converted, $coverage);
        $version = TilesetVersion::fromTime($this->clock->now());
        $this->publisher->publish($tileset, $store, $index, $version, $exportDate);

        return new ImportParcelsOutput(
            $exportDate->toString(),
            $version->value,
            $areas,
            $totalParcels,
            $this->budget->maxBytes,
            array_map(
                static fn(ZoomTileStatistics $zoom): ZoomTilesOutput => new ZoomTilesOutput($zoom->zoom, $zoom->tileCount, $zoom->largestTileBytes),
                $statistics->zooms(),
            ),
            array_map(
                static fn(UnknownCode $code): UnknownCodeOutput => new UnknownCodeOutput($code->list->value, $code->code, $code->parcelCount),
                $store->unknownCodes,
            ),
        );
    }

    private function requireWithinBudget(TilesetStatistics $statistics): void
    {
        if ($statistics->fitsWithin($this->budget)) {
            return;
        }

        $largest = $statistics->largest();

        throw TilesetOverBudget::largestTile(
            $largest->zoom,
            $largest->largestTile->x,
            $largest->largestTile->y,
            $largest->largestTileBytes,
            $this->budget->maxBytes,
        );
    }

    /**
     * @param non-empty-list<ConvertedParcels> $converted one per municipality
     */
    private function parcelCount(array $converted, CadastralArea $area): int
    {
        foreach ($converted as $parcels) {
            if ($parcels->municipality->equals($area->municipality->code)) {
                return $parcels->parcelCountOf($area->code);
            }
        }

        throw new LogicException(sprintf('The municipality %d was not converted.', $area->municipality->code->value));
    }

    private function exportDate(ImportParcelsInput $input): ExportDate
    {
        if ($input->exportDate === null) {
            return ExportDate::latestPublishedOn($this->clock->now());
        }

        try {
            return ExportDate::fromString($input->exportDate);
        } catch (InvalidExportDate $exception) {
            throw InvalidImportRequest::invalidExportDate($input->exportDate, $exception->getMessage());
        }
    }

    private function coverage(): Coverage
    {
        try {
            return $this->coverage->get();
        } catch (CoverageUnavailable $exception) {
            throw CoverageNotAvailable::because($exception->getMessage());
        }
    }

    /**
     * Files appear on the first day of the month, so only the first download may fall back, once, to the month
     * before; every later municipality then uses the same date.
     *
     * @param non-empty-list<MunicipalityCode> $municipalities
     * @return non-empty-array<int, DownloadedExport> by municipality code
     */
    private function download(array $municipalities, ExportDate $date, bool $allowFallback, ImportProgress $progress): array
    {
        $total = count($municipalities);
        $first = $this->downloadFirst($municipalities[0], $date, $allowFallback, $progress);
        $progress->exportDownloaded($first->municipality->value, 1, $total, $first->fromCache);
        $exports = [$first->municipality->value => $first];

        foreach (array_slice($municipalities, 1, preserve_keys: true) as $index => $municipality) {
            $exports[$municipality->value] = $this->downloader->download($municipality, $first->date);
            $progress->exportDownloaded($municipality->value, $index + 1, $total, $exports[$municipality->value]->fromCache);
        }

        return $exports;
    }

    private function downloadFirst(MunicipalityCode $municipality, ExportDate $date, bool $allowFallback, ImportProgress $progress): DownloadedExport
    {
        try {
            return $this->downloader->download($municipality, $date);
        } catch (ExportNotPublished $exception) {
            if (!$allowFallback) {
                throw $exception;
            }

            $previous = $date->previous();
            $export = $this->downloader->download($municipality, $previous);
            $progress->exportDateFellBack($date->toString(), $previous->toString());

            return $export;
        }
    }
}
