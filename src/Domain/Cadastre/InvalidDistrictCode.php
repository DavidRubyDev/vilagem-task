<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidDistrictCode extends InvalidArgumentException
{
    public static function notFourDigits(int $value): self
    {
        return new self(sprintf('A district code must have four digits, %d given.', $value));
    }
}
