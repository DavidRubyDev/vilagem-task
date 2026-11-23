<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Command;

use DateTimeImmutable;
use DateTimeZone;
use ParcelMap\Application\Import\CodeList;
use ParcelMap\Application\Import\ImportParcelsUseCase;
use ParcelMap\Application\Import\UnknownCode;
use ParcelMap\Command\Console\ExitCode;
use ParcelMap\Command\ImportParcelsCommand;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\TilesetStatistics;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Domain\Map\ZoomTileStatistics;
use ParcelMap\Tests\Fake\ClockUsingFixedTime;
use ParcelMap\Tests\Fake\CoverageRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelConverterUsingMemory;
use ParcelMap\Tests\Fake\ParcelDataPublisherUsingMemory;
use ParcelMap\Tests\Fake\ParcelStoreBuilderUsingMemory;
use ParcelMap\Tests\Fake\RuianExportDownloaderUsingMemory;
use ParcelMap\Tests\Fake\TilesetBuilderUsingMemory;
use ParcelMap\Tests\Fake\TilesetInspectorUsingMemory;
use ParcelMap\Tests\Unit\Command\Console\MemoryConsoleOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportParcelsCommandTest extends TestCase
{
    #[Test]
    public function it_warns_on_stderr_about_each_unknown_code_and_still_succeeds(): void
    {
        $output = new MemoryConsoleOutput();

        $exitCode = $this->command([
            new UnknownCode(CodeList::LandUse, 31, 3),
            new UnknownCode(CodeList::LandType, 99, 1),
        ])->run(['--export-date=2026-10-31'], $output);

        self::assertSame(ExitCode::SUCCESS, $exitCode);
        self::assertSame(
            [
                'Warning: unknown land use code 31 on 3 parcels; the map shows it as "Unknown land use (code 31)".',
                'Warning: unknown land type code 99 on 1 parcel; the map shows it as "Unknown land type (code 99)".',
            ],
            $output->stderr,
        );
    }

    #[Test]
    public function it_prints_the_published_line_last_and_no_warning_without_unknown_codes(): void
    {
        $output = new MemoryConsoleOutput();

        $exitCode = $this->command([])->run(['--export-date=2026-10-31'], $output);

        self::assertSame(ExitCode::SUCCESS, $exitCode);
        self::assertSame([], $output->stderr);
        self::assertSame('Published tileset and parcel store 20261120093000 with 5 parcels.', end($output->stdout));
    }

    /**
     * @param list<UnknownCode> $unknownCodes
     */
    private function command(array $unknownCodes): ImportParcelsCommand
    {
        $municipality = new Municipality(new MunicipalityCode(572659), 'Jičín');
        $coverage = new Coverage(new CadastralArea(new CadastralAreaCode(659541), 'Jičín', $municipality));

        return new ImportParcelsCommand(new ImportParcelsUseCase(
            new CoverageRepositoryUsingMemory($coverage),
            new RuianExportDownloaderUsingMemory(['2026-10-31'], []),
            new ParcelConverterUsingMemory([659541 => 5], false),
            new TilesetBuilderUsingMemory(),
            new TilesetInspectorUsingMemory(new TilesetStatistics(new ZoomTileStatistics(9, 4, 100_000, new TileCoordinate(9, 277, 172)))),
            new ParcelStoreBuilderUsingMemory(false, $unknownCodes),
            new ParcelDataPublisherUsingMemory(),
            new ClockUsingFixedTime(new DateTimeImmutable('2026-11-20 09:30:00', new DateTimeZone('UTC'))),
            new TileSizeBudget(150_000),
        ));
    }
}
