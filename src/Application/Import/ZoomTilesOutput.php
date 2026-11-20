<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final readonly class ZoomTilesOutput
{
    public function __construct(
        public int $zoom,
        public int $tileCount,
        public int $largestTileBytes,
    ) {}
}
