<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

/** The kind of a house number; the value is the building type TypStavebnihoObjektuKod: 1 číslo popisné, 2 číslo evidenční. */
enum HouseNumberKind: int
{
    case Descriptive = 1;
    case Registration = 2;

    /**
     * @throws InvalidHouseNumber for a building type that has no house number
     */
    public static function fromCode(int $code): self
    {
        return self::tryFrom($code) ?? throw InvalidHouseNumber::unknownKind($code);
    }
}
