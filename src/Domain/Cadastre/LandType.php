<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/**
 * The land type (druh pozemku), RÚIAN DruhPozemkuKod. Any positive code is kept, also one outside the published
 * code list: the import reports it and the map shows it as unknown, instead of failing.
 */
final readonly class LandType
{
    private const array KNOWN_CODES = [
        2, // orná půda
        3, // chmelnice
        4, // vinice
        5, // zahrada
        6, // ovocný sad
        7, // trvalý travní porost
        8, // trvalý travní porost
        10, // lesní pozemek
        11, // vodní plocha
        13, // zastavěná plocha a nádvoří
        14, // ostatní plocha
    ];

    public function __construct(public int $code)
    {
        if ($code < 1) {
            throw InvalidLandType::notPositive($code);
        }
    }

    public function isKnown(): bool
    {
        return in_array($this->code, self::KNOWN_CODES, true);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
