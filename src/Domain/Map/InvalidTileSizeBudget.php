<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidTileSizeBudget extends InvalidArgumentException
{
    public static function notPositive(int $maxBytes): self
    {
        return new self(sprintf('A tile size budget must be at least one byte, %d given.', $maxBytes));
    }
}
