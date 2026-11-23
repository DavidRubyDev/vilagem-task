<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/**
 * The land use (způsob využití pozemku), RÚIAN ZpusobyVyuzitiPozemku. Optional on a parcel. Any positive code is
 * kept, also one outside the published code list (see LandType).
 */
final readonly class LandUse
{
    private const int LARGEST_KNOWN_CODE = 30;
    // 1 skleník, pařeniště; 2 školka; 3 plantáž dřevin; 4 les jiný než hospodářský; 5 lesní pozemek, na kterém je budova;
    // 6 rybník; 7 koryto vodního toku přirozené nebo upravené; 8 koryto vodního toku umělé; 9 vodní nádrž přírodní;
    // 10 vodní nádrž umělá; 11 zamokřená plocha; 12 společný dvůr; 13 zbořeniště; 14 dráha; 15 dálnice; 16 silnice;
    // 17 ostatní komunikace; 18 ostatní dopravní plocha; 19 zeleň; 20 sportoviště a rekreační plocha; 21 pohřebiště;
    // 22 kulturní a osvětová plocha; 23 manipulační plocha; 24 dobývací prostor; 25 skládka; 26 jiná plocha;
    // 27 neplodná půda; 28 vodní plocha, na které je budova; 29 fotovoltaická elektrárna; 30 mez, stráň.

    public function __construct(public int $code)
    {
        if ($code < 1) {
            throw InvalidLandUse::notPositive($code);
        }
    }

    public function isKnown(): bool
    {
        return $this->code <= self::LARGEST_KNOWN_CODE;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
