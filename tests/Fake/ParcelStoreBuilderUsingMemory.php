<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\BuiltParcelStore;
use ParcelMap\Application\Import\ParcelStoreBuilder;
use ParcelMap\Application\Import\ParcelStoreBuildFailed;
use ParcelMap\Application\Import\UnknownCode;
use ParcelMap\Domain\Cadastre\Coverage;

final class ParcelStoreBuilderUsingMemory implements ParcelStoreBuilder
{
    /** @var list<string> paths of the converted files of every build, in the given order */
    public array $builtFrom = [];

    /** The coverage of the last build, null before the first build */
    public ?Coverage $coverage = null;

    /**
     * @param list<UnknownCode> $unknownCodes
     */
    public function __construct(
        private readonly bool $failing = false,
        private readonly array $unknownCodes = [],
    ) {}

    public function build(array $parcels, Coverage $coverage): BuiltParcelStore
    {
        if ($this->failing) {
            throw ParcelStoreBuildFailed::because('the fake store builder fails');
        }
        $this->coverage = $coverage;
        foreach ($parcels as $converted) {
            $this->builtFrom[] = $converted->path;
        }

        return new BuiltParcelStore('memory://parcel-store', $this->unknownCodes);
    }
}
