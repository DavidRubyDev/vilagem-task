<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;

final class InvalidConvertedParcels extends InvalidArgumentException
{
    public static function noParcels(int $area): self
    {
        return new self(sprintf('The cadastral area %d has no converted parcels.', $area));
    }

    public static function noAreas(): self
    {
        return new self('Converted parcels must cover at least one cadastral area.');
    }

    public static function duplicateArea(int $area): self
    {
        return new self(sprintf('The cadastral area %d is counted more than once.', $area));
    }
}
