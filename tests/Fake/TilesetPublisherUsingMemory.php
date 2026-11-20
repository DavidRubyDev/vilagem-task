<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TilesetPublisher;
use ParcelMap\Application\Import\TilesetPublishFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;

final class TilesetPublisherUsingMemory implements TilesetPublisher
{
    /** @var list<string> "<path> <version> <export date>" of every publication */
    public array $published = [];

    public function __construct(private readonly bool $failing = false) {}

    public function publish(BuiltTileset $tileset, TilesetVersion $version, ExportDate $exportDate): void
    {
        if ($this->failing) {
            throw TilesetPublishFailed::because('the fake publisher fails');
        }
        $this->published[] = $tileset->path . ' ' . $version->value . ' ' . $exportDate->toString();
    }
}
