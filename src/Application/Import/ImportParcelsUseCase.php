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
use ParcelMap\Domain\Map\TilesetVersion;

final readonly class ImportParcelsUseCase
{
    public function __construct(
        private CoverageRepository $coverage,
        private RuianExportDownloader $downloader,
        private ParcelConverter $converter,
        private TilesetBuilder $builder,
        private TilesetPublisher $publisher,
        private Clock $clock,
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
        $tileset = $this->builder->build($converted);
        $version = TilesetVersion::fromTime($this->clock->now());
        $this->publisher->publish($tileset, $version, $exportDate);

        return new ImportParcelsOutput(
            $exportDate->toString(),
            $version->value,
            $areas,
            $totalParcels,
        );
    }

    /**
     * @param non-empty-list<ConvertedParcels> $converted one per municipality
     */
    private function parcelCount(array $converted, CadastralArea $area): int
    {
        foreach ($converted as $parcels) {
            if ($parcels->municipality->equals($area->municipality)) {
                return $parcels->parcelCountOf($area->code);
            }
        }

        throw new LogicException(sprintf('The municipality %d was not converted.', $area->municipality->value));
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
