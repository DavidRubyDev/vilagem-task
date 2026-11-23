<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidArea extends InvalidArgumentException
{
    public static function notPositive(int $squareMetres): self
    {
        return new self(sprintf('An area must be at least 1 square metre, %d given.', $squareMetres));
    }
}
