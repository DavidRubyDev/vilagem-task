<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\Coverage;

interface SearchIndexBuilder
{
    /**
     * Builds the search index of all converted parcels; area and municipality names come from the coverage.
     *
     * @param non-empty-list<ConvertedParcels> $parcels
     * @throws SearchIndexBuildFailed
     */
    public function build(array $parcels, Coverage $coverage): BuiltSearchIndex;
}
