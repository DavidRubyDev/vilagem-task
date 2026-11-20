<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

use ParcelMap\Application\Clock\Clock;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageNotSaved;
use ParcelMap\Domain\Cadastre\CoverageRepository;
use ParcelMap\Domain\Cadastre\DistrictCode;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\InvalidDistrictCode;
use ParcelMap\Domain\Cadastre\InvalidExportDate;

final readonly class GenerateCoverageUseCase
{
    public function __construct(
        private RuianStructureDownloader $downloader,
        private DistrictAreaExtractor $extractor,
        private CoverageRepository $coverage,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidCoverageRequest
     * @throws CoverageGenerationFailed
     */
    public function execute(GenerateCoverageInput $input): GenerateCoverageOutput
    {
        $district = $this->district($input->district);
        $date = $this->exportDate($input);

        $structure = $this->download($date, allowFallback: $input->exportDate === null);
        $areas = $this->extractor->extract($structure, $district);
        if ($areas === []) {
            throw DistrictNotFound::for($district->value, $structure->date->toString());
        }

        $coverage = Coverage::sortedByCode(...$areas);
        $this->save($coverage);

        return new GenerateCoverageOutput(
            $structure->date->toString(),
            $district->value,
            count($coverage->areas()),
            count($coverage->municipalities()),
        );
    }

    private function district(int $code): DistrictCode
    {
        try {
            return new DistrictCode($code);
        } catch (InvalidDistrictCode $exception) {
            throw InvalidCoverageRequest::invalidDistrict($code, $exception->getMessage());
        }
    }

    private function exportDate(GenerateCoverageInput $input): ExportDate
    {
        if ($input->exportDate === null) {
            return ExportDate::latestPublishedOn($this->clock->now());
        }

        try {
            return ExportDate::fromString($input->exportDate);
        } catch (InvalidExportDate $exception) {
            throw InvalidCoverageRequest::invalidExportDate($input->exportDate, $exception->getMessage());
        }
    }

    /**
     * The file appears on the first day of the month, so a request for the latest one may fall back, once, to
     * the month before.
     */
    private function download(ExportDate $date, bool $allowFallback): DownloadedStructure
    {
        try {
            return $this->downloader->download($date);
        } catch (StructureNotPublished $exception) {
            if (!$allowFallback) {
                throw $exception;
            }

            return $this->downloader->download($date->previous());
        }
    }

    private function save(Coverage $coverage): void
    {
        try {
            $this->coverage->save($coverage);
        } catch (CoverageNotSaved $exception) {
            throw CoverageWriteFailed::because($exception->getMessage());
        }
    }
}
