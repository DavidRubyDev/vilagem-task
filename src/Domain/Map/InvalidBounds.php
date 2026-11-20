<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidBounds extends InvalidArgumentException
{
    public static function notOrdered(): self
    {
        return new self('The south-west corner must lie south and west of the north-east corner.');
    }
}
