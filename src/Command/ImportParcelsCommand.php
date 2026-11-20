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
        return 'Download the RÚIAN export and build the parcel tileset.';
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
            $result = $this->useCase->execute(new ImportParcelsInput($exportDate));
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
        $output->writeLine(sprintf('Published tileset %s with %d parcels.', $result->tilesetVersion, $result->totalParcels));

        return ExitCode::SUCCESS;
    }
}
