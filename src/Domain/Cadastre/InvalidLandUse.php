<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidLandUse extends InvalidArgumentException
{
    public static function notPositive(int $code): self
    {
        return new self(sprintf('A land use code must be positive, %d given.', $code));
    }
}
