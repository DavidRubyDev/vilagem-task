<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;

final class InvalidUnknownCode extends InvalidArgumentException
{
    public static function noParcels(int $code): self
    {
        return new self(sprintf('The unknown code %d must be used by at least one parcel.', $code));
    }
}
