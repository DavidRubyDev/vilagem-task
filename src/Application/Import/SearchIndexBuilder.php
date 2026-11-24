<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\Coverage;

interface SearchIndexBuilder
{
    /**
     * Builds the search index of all converted parcels and of the places of every municipality; area and
     * municipality names come from the coverage.
     *
     * @param non-empty-list<ConvertedParcels> $parcels
     * @param non-empty-list<ConvertedPlaces> $places one per municipality, in coverage order
     * @throws SearchIndexBuildFailed
     */
    public function build(array $parcels, array $places, Coverage $coverage): BuiltSearchIndex;
}
