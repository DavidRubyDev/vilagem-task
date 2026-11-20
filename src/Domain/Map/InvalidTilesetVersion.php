<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidTilesetVersion extends InvalidArgumentException
{
    public static function malformed(string $value): self
    {
        return new self(sprintf('A tileset version has 1 to 32 letters, digits or hyphens, "%s" given.', $value));
    }
}
