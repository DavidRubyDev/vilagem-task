<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidZoomLevel extends InvalidArgumentException
{
    public static function outOfRange(float $value): self
    {
        return new self(sprintf('Zoom level must be a finite number between 0 and 24, %s given.', var_export($value, true)));
    }
}
