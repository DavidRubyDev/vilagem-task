<?php

declare(strict_types=1);

namespace ParcelMap\Application\Parcel;

final readonly class ParcelDetailOutput
{
    /**
     * @param 'building'|'land' $kind
     * @param list<list<array{0: float, 1: float}>> $boundaryRings [longitude, latitude] positions, outer ring first
     */
    public function __construct(
        public int $id,
        public string $parcelNumber,
        public string $kind,
        public int $cadastralAreaCode,
        public string $cadastralAreaName,
        public int $municipalityCode,
        public string $municipalityName,
        public int $landTypeCode,
        public ?int $landUseCode,
        public int $areaSquareMetres,
        public array $boundaryRings,
    ) {}
}
