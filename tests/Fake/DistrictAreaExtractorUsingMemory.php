<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Coverage\DistrictAreaExtractor;
use ParcelMap\Application\Coverage\DownloadedStructure;
use ParcelMap\Application\Coverage\StructureReadFailed;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\DistrictCode;

final class DistrictAreaExtractorUsingMemory implements DistrictAreaExtractor
{
    /** @var list<string> "<district> from <structure path>" of every extraction, in order */
    public array $extractions = [];

    /**
     * @param list<CadastralArea> $areas returned for every district
     */
    public function __construct(
        private readonly array $areas,
        private readonly ?StructureReadFailed $failure = null,
    ) {}

    public function extract(DownloadedStructure $structure, DistrictCode $district): array
    {
        $this->extractions[] = $district->value . ' from ' . $structure->path;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->areas;
    }
}
