<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/**
 * The RÚIAN parcel Id. It is the vector tile feature id and a JSON number in the browser, and both are exact only
 * up to 2^53 - 1, so a larger id is rejected.
 */
final readonly class ParcelId
{
    private const int LARGEST = 9007199254740991;

    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::LARGEST) {
            throw InvalidParcelId::outOfRange($value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
