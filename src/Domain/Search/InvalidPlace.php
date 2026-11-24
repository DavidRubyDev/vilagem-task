<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use InvalidArgumentException;

final class InvalidPlace extends InvalidArgumentException
{
    public static function codeNotPositive(int $code): self
    {
        return new self(sprintf('The code of a place must be at least 1, %d given.', $code));
    }

    public static function blankName(int $code): self
    {
        return new self(sprintf('The place %d has a blank name.', $code));
    }

    public static function parcelOnlyForAddresses(int $code): self
    {
        return new self(sprintf('Only an address has a parcel; the place %d is not an address.', $code));
    }

    public static function centerOutsideBounds(int $code): self
    {
        return new self(sprintf('The centre of the place %d lies outside its bounds.', $code));
    }
}
