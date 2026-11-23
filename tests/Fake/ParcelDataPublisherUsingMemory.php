<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltParcelStore;
use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\ParcelDataPublisher;
use ParcelMap\Application\Import\ParcelDataPublishFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Map\TilesetVersion;

final class ParcelDataPublisherUsingMemory implements ParcelDataPublisher
{
    /** @var list<string> "<tileset path> <store path> <version> <export date>" of every publication */
    public array $published = [];

    public function __construct(private readonly bool $failing = false) {}

    public function publish(BuiltTileset $tileset, BuiltParcelStore $store, TilesetVersion $version, ExportDate $exportDate): void
    {
        if ($this->failing) {
            throw ParcelDataPublishFailed::because('the fake publisher fails');
        }
        $this->published[] = $tileset->path . ' ' . $store->path . ' ' . $version->value . ' ' . $exportDate->toString();
    }
}
