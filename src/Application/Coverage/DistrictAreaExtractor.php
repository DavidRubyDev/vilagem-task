<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\DistrictCode;

interface DistrictAreaExtractor
{
    /**
     * The cadastral areas with a digital map of every municipality in the district; empty when there are none.
     *
     * @return list<CadastralArea>
     * @throws StructureReadFailed
     */
    public function extract(DownloadedStructure $structure, DistrictCode $district): array;
}
