<?php

declare(strict_types=1);

namespace ParcelMap\Command;

use ParcelMap\Application\Coverage\CoverageGenerationFailed;
use ParcelMap\Application\Coverage\GenerateCoverageInput;
use ParcelMap\Application\Coverage\GenerateCoverageUseCase;
use ParcelMap\Application\Coverage\InvalidCoverageRequest;
use ParcelMap\Command\Console\Command;
use ParcelMap\Command\Console\ConsoleOutput;
use ParcelMap\Command\Console\ExitCode;

final readonly class GenerateCoverageCommand implements Command
{
    private const string USAGE = 'Usage: bin/console coverage:generate --district=CODE [--export-date=YYYY-MM-DD]';
    private const string DISTRICT_OPTION = '--district=';
    private const string EXPORT_DATE_OPTION = '--export-date=';

    public function __construct(private GenerateCoverageUseCase $useCase) {}

    public function name(): string
    {
        return 'coverage:generate';
    }

    public function description(): string
    {
        return 'Write the cadastral areas of a district to the coverage list.';
    }

    public function run(array $arguments, ConsoleOutput $output): int
    {
        $district = null;
        $exportDate = null;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, self::DISTRICT_OPTION) && $district === null) {
                $district = substr($argument, strlen(self::DISTRICT_OPTION));
            } elseif (str_starts_with($argument, self::EXPORT_DATE_OPTION) && $exportDate === null) {
                $exportDate = substr($argument, strlen(self::EXPORT_DATE_OPTION));
            } else {
                $output->writeError(sprintf('Unknown option "%s".', $argument));
                $output->writeError(self::USAGE);

                return ExitCode::USAGE;
            }
        }
        if ($district === null) {
            $output->writeError(self::USAGE);

            return ExitCode::USAGE;
        }
        if (!ctype_digit($district)) {
            $output->writeError(sprintf('Invalid request: "%s" cannot be used as a district code: it is not a number.', $district));

            return ExitCode::USAGE;
        }

        try {
            $result = $this->useCase->execute(new GenerateCoverageInput((int) $district, $exportDate));
        } catch (InvalidCoverageRequest $exception) {
            $output->writeError('Invalid request: ' . $exception->getMessage());

            return ExitCode::USAGE;
        } catch (CoverageGenerationFailed $exception) {
            $output->writeError('Coverage generation failed: ' . $exception->getMessage());

            return ExitCode::FAILURE;
        }

        $output->writeLine(sprintf(
            'Wrote %d cadastral %s of %d %s in district %d from the RÚIAN export of %s.',
            $result->areaCount,
            $result->areaCount === 1 ? 'area' : 'areas',
            $result->municipalityCount,
            $result->municipalityCount === 1 ? 'municipality' : 'municipalities',
            $result->district,
            $result->exportDate,
        ));

        return ExitCode::SUCCESS;
    }
}
