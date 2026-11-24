<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelNumber;

/** A parcel found by its number. */
final readonly class ParcelMatch
{
    public function __construct(
        public ParcelId $id,
        public ParcelNumber $number,
        public CadastralArea $cadastralArea,
    ) {}

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
