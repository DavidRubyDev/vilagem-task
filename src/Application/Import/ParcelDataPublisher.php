<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;

interface ParcelDataPublisher
{
    /**
     * Publishes the tileset, the parcel store and the search index of one import under one version. Readers see either
     * the old or the new parcel data, never a tileset without its store and index.
     *
     * @throws ParcelDataPublishFailed
     */
    public function publish(BuiltTileset $tileset, BuiltParcelStore $store, BuiltSearchIndex $index, TilesetVersion $version, ExportDate $exportDate): void;
}
