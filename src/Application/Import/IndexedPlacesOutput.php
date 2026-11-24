<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final readonly class IndexedPlacesOutput
{
    public function __construct(
        public int $municipalities,
        public int $cadastralAreas,
        public int $municipalityParts,
        public int $streets,
        public int $addresses,
    ) {}
}
