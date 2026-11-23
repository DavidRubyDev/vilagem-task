<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\ImportProgress;

final class ImportProgressUsingMemory implements ImportProgress
{
    /** @var list<string> every event as text, in order */
    public array $events = [];

    public function importStarted(int $areaCount, int $municipalityCount): void
    {
        $this->events[] = sprintf('started %d areas, %d municipalities', $areaCount, $municipalityCount);
    }

    public function exportDateFellBack(string $unpublishedDate, string $usedDate): void
    {
        $this->events[] = sprintf('fell back from %s to %s', $unpublishedDate, $usedDate);
    }

    public function exportDownloaded(int $municipality, int $position, int $total, bool $fromCache): void
    {
        $this->events[] = sprintf('downloaded %d %d/%d %s', $municipality, $position, $total, $fromCache ? 'cached' : 'downloaded');
    }

    public function municipalityConverted(int $municipality, int $position, int $total, int $areaCount, int $parcelCount): void
    {
        $this->events[] = sprintf('converted %d %d/%d %d areas, %d parcels', $municipality, $position, $total, $areaCount, $parcelCount);
    }

    public function tilesetBuildStarted(int $parcelCount): void
    {
        $this->events[] = sprintf('build from %d parcels', $parcelCount);
    }

    public function parcelStoreBuildStarted(int $parcelCount): void
    {
        $this->events[] = sprintf('parcel store from %d parcels', $parcelCount);
    }
}
