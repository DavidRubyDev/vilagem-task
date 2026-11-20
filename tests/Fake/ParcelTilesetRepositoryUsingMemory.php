<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\ParcelTilesetRepository;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\VectorTile;

final readonly class ParcelTilesetRepositoryUsingMemory implements ParcelTilesetRepository
{
    /**
     * @param array<string, VectorTile> $tiles keyed by "z/x/y"
     */
    public function __construct(
        private ?ParcelTileset $tileset,
        private array $tiles = [],
    ) {}

    public function current(): ParcelTileset
    {
        return $this->tileset ?? throw ParcelDataNotImported::create();
    }

    public function tile(TileCoordinate $coordinate): VectorTile
    {
        $this->current();

        return $this->tiles[$coordinate->zoom . '/' . $coordinate->x . '/' . $coordinate->y] ?? VectorTile::empty();
    }
}
