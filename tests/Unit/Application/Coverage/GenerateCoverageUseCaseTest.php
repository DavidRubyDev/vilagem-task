<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Coverage;

use DateTimeImmutable;
use DateTimeZone;
use ParcelMap\Application\Coverage\CoverageWriteFailed;
use ParcelMap\Application\Coverage\DistrictNotFound;
use ParcelMap\Application\Coverage\GenerateCoverageInput;
use ParcelMap\Application\Coverage\GenerateCoverageUseCase;
use ParcelMap\Application\Coverage\InvalidCoverageRequest;
use ParcelMap\Application\Coverage\StructureNotPublished;
use ParcelMap\Application\Coverage\StructureReadFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\CoverageNotSaved;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Tests\Fake\ClockUsingFixedTime;
use ParcelMap\Tests\Fake\CoverageRepositoryUsingMemory;
use ParcelMap\Tests\Fake\DistrictAreaExtractorUsingMemory;
use ParcelMap\Tests\Fake\RuianStructureDownloaderUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenerateCoverageUseCaseTest extends TestCase
{
    private RuianStructureDownloaderUsingMemory $downloader;
    private DistrictAreaExtractorUsingMemory $extractor;
    private CoverageRepositoryUsingMemory $repository;

    #[Test]
    public function it_saves_the_extracted_areas_sorted_by_code_and_reports_the_counts(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31']);

        $output = $useCase->execute(new GenerateCoverageInput(3604, '2026-10-31'));

        self::assertSame(['2026-10-31'], $this->downloader->attempts);
        self::assertSame(['3604 from memory://structure/2026-10-31'], $this->extractor->extractions);
        self::assertNotNull($this->repository->saved);
        self::assertSame(
            [601462, 659541, 740225],
            array_map(static fn(CadastralArea $area): int => $area->code->value, $this->repository->saved->areas()),
        );
        self::assertSame('2026-10-31', $output->exportDate);
        self::assertSame(3604, $output->district);
        self::assertSame(3, $output->areaCount);
        self::assertSame(2, $output->municipalityCount);
    }

    #[Test]
    public function it_falls_back_once_to_the_previous_month_when_the_latest_file_is_not_published(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-09-30']);

        $output = $useCase->execute(new GenerateCoverageInput(3604, null));

        self::assertSame(['2026-10-31', '2026-09-30'], $this->downloader->attempts);
        self::assertSame('2026-09-30', $output->exportDate);
    }

    #[Test]
    public function it_uses_the_latest_file_without_a_fallback_when_it_is_published(): void
    {
        $output = $this->useCase(publishedDates: ['2026-10-31', '2026-09-30'])->execute(new GenerateCoverageInput(3604, null));

        self::assertSame(['2026-10-31'], $this->downloader->attempts);
        self::assertSame('2026-10-31', $output->exportDate);
    }

    #[Test]
    public function it_fails_when_neither_of_the_last_two_files_is_published(): void
    {
        $useCase = $this->useCase(publishedDates: []);

        try {
            $useCase->execute(new GenerateCoverageInput(3604, null));
            self::fail('Expected StructureNotPublished.');
        } catch (StructureNotPublished) {
            self::assertSame(['2026-10-31', '2026-09-30'], $this->downloader->attempts);
            self::assertNull($this->repository->saved);
        }
    }

    #[Test]
    public function it_does_not_fall_back_for_an_explicit_export_date(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-09-30']);

        try {
            $useCase->execute(new GenerateCoverageInput(3604, '2026-10-31'));
            self::fail('Expected StructureNotPublished.');
        } catch (StructureNotPublished $exception) {
            self::assertSame(['2026-10-31'], $this->downloader->attempts);
            self::assertSame('The RÚIAN state structure file of 2026-10-31 is not published.', $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_an_invalid_district_before_downloading_anything(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31']);

        try {
            $useCase->execute(new GenerateCoverageInput(36, '2026-10-31'));
            self::fail('Expected InvalidCoverageRequest.');
        } catch (InvalidCoverageRequest $exception) {
            self::assertStringContainsString('36', $exception->getMessage());
            self::assertSame([], $this->downloader->attempts);
        }
    }

    #[Test]
    public function it_rejects_an_invalid_export_date_before_downloading_anything(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31']);

        try {
            $useCase->execute(new GenerateCoverageInput(3604, '2026-10-30'));
            self::fail('Expected InvalidCoverageRequest.');
        } catch (InvalidCoverageRequest $exception) {
            self::assertStringContainsString('2026-10-30', $exception->getMessage());
            self::assertSame([], $this->downloader->attempts);
        }
    }

    #[Test]
    public function it_reports_a_district_without_cadastral_areas_and_saves_nothing(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], areas: []);

        try {
            $useCase->execute(new GenerateCoverageInput(3605, '2026-10-31'));
            self::fail('Expected DistrictNotFound.');
        } catch (DistrictNotFound $exception) {
            self::assertSame('District 3605 has no cadastral areas in the RÚIAN export of 2026-10-31.', $exception->getMessage());
            self::assertNull($this->repository->saved);
        }
    }

    #[Test]
    public function it_reports_a_coverage_that_cannot_be_saved(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], saveFailure: CoverageNotSaved::because('disk full'));

        $this->expectException(CoverageWriteFailed::class);
        $this->expectExceptionMessage('disk full');

        $useCase->execute(new GenerateCoverageInput(3604, '2026-10-31'));
    }

    #[Test]
    public function it_propagates_a_failure_to_read_the_structure_file_and_saves_nothing(): void
    {
        $useCase = $this->useCase(publishedDates: ['2026-10-31'], readFailure: StructureReadFailed::because('corrupt'));

        try {
            $useCase->execute(new GenerateCoverageInput(3604, '2026-10-31'));
            self::fail('Expected StructureReadFailed.');
        } catch (StructureReadFailed) {
            self::assertNull($this->repository->saved);
        }
    }

    /**
     * @param list<string> $publishedDates
     * @param list<CadastralArea>|null $areas
     */
    private function useCase(
        array $publishedDates,
        ?array $areas = null,
        ?CoverageNotSaved $saveFailure = null,
        ?StructureReadFailed $readFailure = null,
    ): GenerateCoverageUseCase {
        $this->downloader = new RuianStructureDownloaderUsingMemory($publishedDates);
        $this->extractor = new DistrictAreaExtractorUsingMemory($areas ?? self::areas(), $readFailure);
        $this->repository = new CoverageRepositoryUsingMemory(saveFailure: $saveFailure);

        return new GenerateCoverageUseCase(
            $this->downloader,
            $this->extractor,
            $this->repository,
            new ClockUsingFixedTime(new DateTimeImmutable('2026-11-20 09:30:00', new DateTimeZone('UTC'))),
        );
    }

    /**
     * @return list<CadastralArea>
     */
    private static function areas(): array
    {
        return [
            new CadastralArea(new CadastralAreaCode(740225), 'Robousy', new MunicipalityCode(572659)),
            new CadastralArea(new CadastralAreaCode(601462), 'Běchary', new MunicipalityCode(572675)),
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new MunicipalityCode(572659)),
        ];
    }
}
