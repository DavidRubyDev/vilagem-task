<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use RuntimeException;

final class ParcelStoreUnreadable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('The parcel store cannot be read: ' . $reason);
    }
}
