<?php

declare(strict_types=1);

namespace ParcelMap\Command;

use ParcelMap\Application\Import\ImportFailed;
use ParcelMap\Application\Import\ImportParcelsInput;
use ParcelMap\Application\Import\ImportParcelsUseCase;
use ParcelMap\Application\Import\InvalidImportRequest;
use ParcelMap\Command\Console\Command;
use ParcelMap\Command\Console\ConsoleOutput;
use ParcelMap\Command\Console\ExitCode;

final readonly class ImportParcelsCommand implements Command
{
    private const string USAGE = 'Usage: bin/console parcels:import [--export-date=YYYY-MM-DD]';
    private const string EXPORT_DATE_OPTION = '--export-date=';

    public function __construct(private ImportParcelsUseCase $useCase) {}

    public function name(): string
    {
        return 'parcels:import';
    }

    public function description(): string
    {
        return 'Download the RÚIAN export and build the parcel tileset, parcel store and search index.';
    }

    public function run(array $arguments, ConsoleOutput $output): int
    {
        $exportDate = null;
        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, self::EXPORT_DATE_OPTION)) {
                $output->writeError(sprintf('Unknown option "%s".', $argument));
                $output->writeError(self::USAGE);

                return ExitCode::USAGE;
            }
            $exportDate = substr($argument, strlen(self::EXPORT_DATE_OPTION));
        }

        try {
            $result = $this->useCase->execute(new ImportParcelsInput($exportDate), new ImportProgressUsingConsole($output));
        } catch (InvalidImportRequest $exception) {
            $output->writeError('Invalid --export-date: ' . $exception->getMessage());

            return ExitCode::USAGE;
        } catch (ImportFailed $exception) {
            $output->writeError('Import failed: ' . $exception->getMessage());

            return ExitCode::FAILURE;
        }

        $output->writeLine(sprintf('Imported the RÚIAN export of %s.', $result->exportDate));
        foreach ($result->areas as $area) {
            $output->writeLine(sprintf('%d %s: %d parcels', $area->code, $area->name, $area->parcelCount));
        }
        $output->writeLine(sprintf('Tiles per zoom level (budget %.1F kB per tile):', $result->tileSizeBudgetBytes / 1000));
        foreach ($result->zoomLevels as $zoom) {
            $output->writeLine(sprintf(
                '  zoom %d: %d %s, largest %.1F kB',
                $zoom->zoom,
                $zoom->tileCount,
                $zoom->tileCount === 1 ? 'tile' : 'tiles',
                $zoom->largestTileBytes / 1000,
            ));
        }
        $places = $result->indexedPlaces;
        $output->writeLine(sprintf(
            'Indexed places: municipalities %d, cadastral areas %d, parts of municipalities %d, streets %d, addresses %d.',
            $places->municipalities,
            $places->cadastralAreas,
            $places->municipalityParts,
            $places->streets,
            $places->addresses,
        ));
        foreach ($result->unknownCodes as $unknown) {
            $output->writeError(sprintf(
                'Warning: unknown %1$s code %2$d on %3$d %4$s; the map shows it as "Unknown %1$s (code %2$d)".',
                $unknown->codeList,
                $unknown->code,
                $unknown->parcelCount,
                $unknown->parcelCount === 1 ? 'parcel' : 'parcels',
            ));
        }
        $output->writeLine(sprintf(
            'Published tileset, parcel store and search index %s with %d parcels.',
            $result->tilesetVersion,
            $result->totalParcels,
        ));

        return ExitCode::SUCCESS;
    }
}
