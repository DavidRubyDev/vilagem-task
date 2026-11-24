<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;

final class InvalidIndexedPlaceCounts extends InvalidArgumentException
{
    public static function negative(string $kind, int $count): self
    {
        return new self(sprintf('The number of indexed %s must not be negative, %d given.', $kind, $count));
    }
}
