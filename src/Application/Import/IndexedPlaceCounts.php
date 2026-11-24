<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** How many places of each kind the search index holds. */
final readonly class IndexedPlaceCounts
{
    public function __construct(
        public int $municipalities,
        public int $cadastralAreas,
        public int $municipalityParts,
        public int $streets,
        public int $addresses,
    ) {
        foreach (
            [
                'municipalities' => $municipalities,
                'cadastral areas' => $cadastralAreas,
                'parts of municipalities' => $municipalityParts,
                'streets' => $streets,
                'addresses' => $addresses,
            ] as $kind => $count
        ) {
            if ($count < 0) {
                throw InvalidIndexedPlaceCounts::negative($kind, $count);
            }
        }
    }
}
