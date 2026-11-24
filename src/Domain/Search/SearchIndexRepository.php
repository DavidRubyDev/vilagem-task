<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\TilesetVersion;

interface SearchIndexRepository
{
    /** Whether there is an index for this version; says nothing about whether it can be read. */
    public function isPublished(TilesetVersion $version): bool;

    /**
     * Parcels whose number matches the query, nearest to $near first; [] when the query has no parcel number.
     *
     * @return list<ParcelMatch>
     * @throws SearchIndexNotPublished
     * @throws SearchIndexUnreadable
     */
    public function findParcels(TilesetVersion $version, SearchQuery $query, ?Coordinates $near, int $limit): array;

    /**
     * Places whose name or context starts with every place term of the query: the exact name first, then names
     * starting with the text, then by kind and distance from $near; [] when the query searches no places.
     *
     * @return list<Place>
     * @throws SearchIndexNotPublished
     * @throws SearchIndexUnreadable
     */
    public function findPlaces(TilesetVersion $version, SearchQuery $query, ?Coordinates $near, int $limit): array;
}
