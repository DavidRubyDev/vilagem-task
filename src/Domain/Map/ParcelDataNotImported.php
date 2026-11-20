<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use RuntimeException;

final class ParcelDataNotImported extends RuntimeException
{
    public static function create(): self
    {
        return new self('The parcel data has not been imported yet. Run `make data`.');
    }
}
