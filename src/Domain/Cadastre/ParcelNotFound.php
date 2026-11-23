<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use RuntimeException;

final class ParcelNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('There is no parcel with the id %d.', $id));
    }
}
