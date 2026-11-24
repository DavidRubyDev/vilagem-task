<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltSearchIndex;
use ParcelMap\Application\Import\IndexedPlaceCounts;
use ParcelMap\Application\Import\SearchIndexBuilder;
use ParcelMap\Application\Import\SearchIndexBuildFailed;
use ParcelMap\Domain\Cadastre\Coverage;

final class SearchIndexBuilderUsingMemory implements SearchIndexBuilder
{
    /** @var list<string> paths of the converted parcel files of every build, in the given order */
    public array $builtFrom = [];

    /** @var list<string> paths of the converted place files of every build, in the given order */
    public array $placesBuiltFrom = [];

    /** The coverage of the last build, null before the first build */
    public ?Coverage $coverage = null;

    public function __construct(
        private readonly bool $failing = false,
        private readonly IndexedPlaceCounts $counts = new IndexedPlaceCounts(2, 3, 4, 5, 6),
    ) {}

    public function build(array $parcels, array $places, Coverage $coverage): BuiltSearchIndex
    {
        if ($this->failing) {
            throw SearchIndexBuildFailed::because('the fake search index builder fails');
        }
        $this->coverage = $coverage;
        foreach ($parcels as $converted) {
            $this->builtFrom[] = $converted->path;
        }
        foreach ($places as $converted) {
            $this->placesBuiltFrom[] = $converted->path;
        }

        return new BuiltSearchIndex('memory://search-index', $this->counts);
    }
}
