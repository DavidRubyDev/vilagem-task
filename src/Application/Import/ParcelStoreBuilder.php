<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\Coverage;

interface ParcelStoreBuilder
{
    /**
     * Builds the parcel store of all converted parcels; areas and municipality names come from the coverage.
     *
     * @param non-empty-list<ConvertedParcels> $parcels
     * @throws ParcelStoreBuildFailed
     */
    public function build(array $parcels, Coverage $coverage): BuiltParcelStore;
}
