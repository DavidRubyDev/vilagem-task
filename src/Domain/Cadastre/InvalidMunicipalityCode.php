<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidMunicipalityCode extends InvalidArgumentException
{
    public static function notSixDigits(int $value): self
    {
        return new self(sprintf('A municipality code must have six digits, %d given.', $value));
    }
}
