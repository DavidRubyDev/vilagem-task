<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidVectorTile extends InvalidArgumentException
{
    public static function notGzipped(): self
    {
        return new self('Vector tile data must be gzip-compressed.');
    }
}
