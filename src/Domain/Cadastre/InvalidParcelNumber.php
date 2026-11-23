<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidParcelNumber extends InvalidArgumentException
{
    public static function baseNumberNotPositive(int $baseNumber): self
    {
        return new self(sprintf('The base number of a parcel number must be at least 1, %d given.', $baseNumber));
    }

    public static function subdivisionNotPositive(int $subdivision): self
    {
        return new self(sprintf('The subdivision of a parcel number must be at least 1, %d given.', $subdivision));
    }
}
