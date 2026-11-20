<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use RuntimeException;

final class ParcelTilesetUnreadable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('The parcel tileset cannot be read: ' . $reason);
    }
}
