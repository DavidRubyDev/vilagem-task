<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidParcelId extends InvalidArgumentException
{
    public static function outOfRange(int $value): self
    {
        return new self(sprintf('A parcel id must be between 1 and 9007199254740991, %d given.', $value));
    }
}
