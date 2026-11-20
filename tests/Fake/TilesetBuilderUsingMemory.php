<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\TileBuildFailed;
use ParcelMap\Application\Import\TilesetBuilder;
use ParcelMap\Domain\Map\TileSizeBudget;

final class TilesetBuilderUsingMemory implements TilesetBuilder
{
    /** @var list<string> paths of the converted files of every build */
    public array $builtFrom = [];

    /** The budget of the last build, null before the first build */
    public ?TileSizeBudget $budget = null;

    public function __construct(private readonly bool $failing = false) {}

    public function build(array $parcels, TileSizeBudget $budget): BuiltTileset
    {
        if ($this->failing) {
            throw TileBuildFailed::because('the fake builder fails');
        }
        $this->budget = $budget;
        foreach ($parcels as $converted) {
            $this->builtFrom[] = $converted->path;
        }

        return new BuiltTileset('memory://tileset');
    }
}
