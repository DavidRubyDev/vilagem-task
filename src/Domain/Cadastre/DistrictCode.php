<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** Code of a district (okres); Jičín is 3604. */
final readonly class DistrictCode
{
    public function __construct(public int $value)
    {
        if ($value < 1000 || $value > 9999) {
            throw InvalidDistrictCode::notFourDigits($value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
