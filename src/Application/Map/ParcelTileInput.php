<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

final readonly class ParcelTileInput
{
    public function __construct(
        public string $version,
        public int $zoom,
        public int $x,
        public int $y,
    ) {}
}
