<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

final readonly class ParcelResultOutput
{
    /**
     * @param string $parcelNumber as displayed, e.g. "st. 72"
     * @param string $kind 'building' or 'land'
     */
    public function __construct(
        public int $id,
        public string $parcelNumber,
        public string $kind,
        public int $cadastralAreaCode,
        public string $cadastralAreaName,
        public int $municipalityCode,
        public string $municipalityName,
    ) {}
}
