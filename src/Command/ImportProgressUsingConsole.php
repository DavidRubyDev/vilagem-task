<?php

declare(strict_types=1);

namespace ParcelMap\Command;

use ParcelMap\Application\Import\ImportProgress;
use ParcelMap\Command\Console\ConsoleOutput;

/** Prints import progress for the operator, one line per event. */
final readonly class ImportProgressUsingConsole implements ImportProgress
{
    public function __construct(private ConsoleOutput $output) {}

    public function importStarted(int $areaCount, int $municipalityCount): void
    {
        $this->output->writeLine(sprintf(
            'Importing %d cadastral %s of %d %s.',
            $areaCount,
            $areaCount === 1 ? 'area' : 'areas',
            $municipalityCount,
            $municipalityCount === 1 ? 'municipality' : 'municipalities',
        ));
    }

    public function exportDateFellBack(string $unpublishedDate, string $usedDate): void
    {
        $this->output->writeLine(sprintf(
            'The RÚIAN export of %s is not published yet; using the export of %s.',
            $unpublishedDate,
            $usedDate,
        ));
    }

    public function exportDownloaded(int $municipality, int $position, int $total, bool $fromCache): void
    {
        $this->output->writeLine(sprintf(
            'Download %3d/%d: municipality %d (%s)',
            $position,
            $total,
            $municipality,
            $fromCache ? 'cached' : 'downloaded',
        ));
    }

    public function municipalityConverted(int $municipality, int $position, int $total, int $areaCount, int $parcelCount): void
    {
        $this->output->writeLine(sprintf(
            'Convert  %3d/%d: municipality %d, %s, %d parcels',
            $position,
            $total,
            $municipality,
            $areaCount === 1 ? '1 cadastral area' : $areaCount . ' cadastral areas',
            $parcelCount,
        ));
    }

    public function tilesetBuildStarted(int $parcelCount): void
    {
        $this->output->writeLine(sprintf('Building the tileset from %d parcels.', $parcelCount));
    }
}
