<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use InvalidArgumentException;

final class InvalidAddress extends InvalidArgumentException
{
    public static function blankName(string $field): self
    {
        return new self(sprintf('The %s of an address must not be blank.', $field));
    }
}
