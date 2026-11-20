<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TileBuildFailed;
use ParcelMap\Application\Import\TilesetBuilder;

final class TilesetBuilderUsingMemory implements TilesetBuilder
{
    /** @var list<string> paths of the converted files of every build */
    public array $builtFrom = [];

    public function __construct(private readonly bool $failing = false) {}

    public function build(array $parcels): BuiltTileset
    {
        if ($this->failing) {
            throw TileBuildFailed::because('the fake builder fails');
        }
        foreach ($parcels as $converted) {
            $this->builtFrom[] = $converted->path;
        }

        return new BuiltTileset('memory://tileset');
    }
}
