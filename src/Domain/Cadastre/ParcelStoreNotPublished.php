<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use RuntimeException;

final class ParcelStoreNotPublished extends RuntimeException
{
    public static function forVersion(string $version): self
    {
        return new self(sprintf('There is no parcel store for the version "%s". Run `make data`.', $version));
    }
}
