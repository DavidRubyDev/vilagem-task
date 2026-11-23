<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use ParcelMap\Domain\Map\TilesetVersion;

interface ParcelRepository
{
    /**
     * @throws ParcelNotFound no parcel with this id in the store of this version
     * @throws ParcelStoreNotPublished there is no store for this version
     * @throws ParcelStoreUnreadable
     */
    public function get(TilesetVersion $version, ParcelId $id): Parcel;

    /** Whether there is a store for this version; says nothing about whether it can be read. */
    public function isPublished(TilesetVersion $version): bool;
}
