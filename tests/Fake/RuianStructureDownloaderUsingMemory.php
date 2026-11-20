<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Coverage\DownloadedStructure;
use ParcelMap\Application\Coverage\RuianStructureDownloader;
use ParcelMap\Application\Coverage\StructureNotPublished;
use ParcelMap\Domain\Cadastre\ExportDate;

final class RuianStructureDownloaderUsingMemory implements RuianStructureDownloader
{
    /** @var list<string> dates (Y-m-d) of every download attempt, in order */
    public array $attempts = [];

    /**
     * @param list<string> $publishedDates dates (Y-m-d) that are published
     */
    public function __construct(private readonly array $publishedDates) {}

    public function download(ExportDate $date): DownloadedStructure
    {
        $this->attempts[] = $date->toString();
        if (!in_array($date->toString(), $this->publishedDates, true)) {
            throw StructureNotPublished::for($date->toString());
        }

        return new DownloadedStructure($date, 'memory://structure/' . $date->toString(), false);
    }
}
