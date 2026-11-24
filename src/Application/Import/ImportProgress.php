<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** What the operator sees while an import runs. Arguments are primitives; dates are Y-m-d, positions 1-based. */
interface ImportProgress
{
    public function importStarted(int $areaCount, int $municipalityCount): void;

    public function exportDateFellBack(string $unpublishedDate, string $usedDate): void;

    public function exportDownloaded(int $municipality, int $position, int $total, bool $fromCache): void;

    public function municipalityConverted(int $municipality, int $position, int $total, int $areaCount, int $parcelCount): void;

    public function tilesetBuildStarted(int $parcelCount): void;

    public function parcelStoreBuildStarted(int $parcelCount): void;

    public function searchIndexBuildStarted(int $parcelCount, int $municipalityCount): void;
}
