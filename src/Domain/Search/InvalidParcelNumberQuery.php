<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use InvalidArgumentException;

final class InvalidParcelNumberQuery extends InvalidArgumentException
{
    public static function baseNumberOutOfRange(int $baseNumber): self
    {
        return new self(sprintf('The base number of a searched parcel number must be 1 to 99999, %d given.', $baseNumber));
    }

    public static function subdivisionOutOfRange(int $subdivision): self
    {
        return new self(sprintf('The subdivision of a searched parcel number must be 1 to 9999, %d given.', $subdivision));
    }
}
