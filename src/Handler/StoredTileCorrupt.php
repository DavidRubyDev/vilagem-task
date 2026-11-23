<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use RuntimeException;

final class StoredTileCorrupt extends RuntimeException
{
    public static function cannotDecompress(): self
    {
        return new self('A stored tile cannot be decompressed.');
    }
}
