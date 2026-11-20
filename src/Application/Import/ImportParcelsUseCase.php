<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

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
    public function execute(ImportParcelsInput $input): ImportParcelsOutput
    {
        $coverage = $this->coverage();
        $exports = $this->download($coverage, $this->exportDate($input), allowFallback: $input->exportDate === null);

        $converted = array_map(
            fn(CadastralArea $area): ConvertedParcels => $this->converter->convert($exports[$area->municipality->value], $area),
            $coverage->areas(),
        );
        $areas = array_map(
            static fn(CadastralArea $area, ConvertedParcels $parcels): ImportedAreaOutput => new ImportedAreaOutput(
                $area->code->value,
                $area->name,
                $parcels->parcelCount,
            ),
            $coverage->areas(),
            $converted,
        );

        $exportDate = array_first($exports)->date;
        $tileset = $this->builder->build($converted);
        $version = TilesetVersion::fromTime($this->clock->now());
        $this->publisher->publish($tileset, $version, $exportDate);

        return new ImportParcelsOutput(
            $exportDate->toString(),
            $version->value,
            $areas,
            array_sum(array_map(static fn(ImportedAreaOutput $area): int => $area->parcelCount, $areas)),
        );
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
     * @return non-empty-array<int, DownloadedExport> by municipality code
     */
    private function download(Coverage $coverage, ExportDate $date, bool $allowFallback): array
    {
        $municipalities = $coverage->municipalities();
        $first = $this->downloadFirst($municipalities[0], $date, $allowFallback);
        $exports = [$first->municipality->value => $first];

        foreach (array_slice($municipalities, 1) as $municipality) {
            $exports[$municipality->value] = $this->downloader->download($municipality, $first->date);
        }

        return $exports;
    }

    private function downloadFirst(MunicipalityCode $municipality, ExportDate $date, bool $allowFallback): DownloadedExport
    {
        try {
            return $this->downloader->download($municipality, $date);
        } catch (ExportNotPublished $exception) {
            if (!$allowFallback) {
                throw $exception;
            }

            return $this->downloader->download($municipality, $date->previous());
        }
    }
}
