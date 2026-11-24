<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltSearchIndex;
use ParcelMap\Application\Import\SearchIndexBuilder;
use ParcelMap\Application\Import\SearchIndexBuildFailed;
use ParcelMap\Domain\Cadastre\Coverage;

final class SearchIndexBuilderUsingMemory implements SearchIndexBuilder
{
    /** @var list<string> paths of the converted files of every build, in the given order */
    public array $builtFrom = [];

    /** The coverage of the last build, null before the first build */
    public ?Coverage $coverage = null;

    public function __construct(private readonly bool $failing = false) {}

    public function build(array $parcels, Coverage $coverage): BuiltSearchIndex
    {
        if ($this->failing) {
            throw SearchIndexBuildFailed::because('the fake search index builder fails');
        }
        $this->coverage = $coverage;
        foreach ($parcels as $converted) {
            $this->builtFrom[] = $converted->path;
        }

        return new BuiltSearchIndex('memory://search-index');
    }
}
