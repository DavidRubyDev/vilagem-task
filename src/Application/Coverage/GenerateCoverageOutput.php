<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

final readonly class GenerateCoverageOutput
{
    /**
     * @param string $exportDate Y-m-d
     */
    public function __construct(
        public string $exportDate,
        public int $district,
        public int $areaCount,
        public int $municipalityCount,
    ) {}
}
