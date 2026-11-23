<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** A land parcel (parcela); identity is its RÚIAN id. */
final readonly class Parcel
{
    // ČÚZK's public viewer shows the owners and the LV number, which are not in the open data.
    private const string CADASTRE_VIEWER_URL = 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=%d';

    public function __construct(
        public ParcelId $id,
        public CadastralArea $cadastralArea,
        public ParcelNumber $number,
        public LandType $landType,
        public ?LandUse $landUse,
        public Area $area,
        public ParcelBoundary $boundary,
    ) {}

    public function cadastreViewerUrl(): string
    {
        return sprintf(self::CADASTRE_VIEWER_URL, $this->id->value);
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
