<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** A land parcel (parcela); identity is its RÚIAN id. */
final readonly class Parcel
{
    public function __construct(
        public ParcelId $id,
        public CadastralArea $cadastralArea,
        public ParcelNumber $number,
        public LandType $landType,
        public ?LandUse $landUse,
        public Area $area,
        public ParcelBoundary $boundary,
    ) {}

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
