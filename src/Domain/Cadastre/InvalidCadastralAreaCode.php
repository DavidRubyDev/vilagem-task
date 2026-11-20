<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidCadastralAreaCode extends InvalidArgumentException
{
    public static function notSixDigits(int $value): self
    {
        return new self(sprintf('A cadastralarea code must have six digits, %d given.', $value));
    }
}
