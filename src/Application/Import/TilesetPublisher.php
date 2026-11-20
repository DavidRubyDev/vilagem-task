<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;

interface TilesetPublisher
{
    /**
     * @throws TilesetPublishFailed
     */
    public function publish(BuiltTileset $tileset, TilesetVersion $version, ExportDate $exportDate): void;
}
