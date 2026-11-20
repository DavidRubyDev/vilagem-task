<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

final readonly class ParcelTileOutput
{
    /**
     * @param string $gzippedData the gzip-compressed tile, an empty string when $empty
     */
    public function __construct(
        public bool $empty,
        public string $gzippedData,
    ) {}
}
