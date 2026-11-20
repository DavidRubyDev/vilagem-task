<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidParcelTileset extends InvalidArgumentException
{
    public static function zoomRange(int $minZoom, int $maxZoom): self
    {
        return new self(sprintf('The zoom range %d to %d is not valid; it must lie within 0 to 24 and not be reversed.', $minZoom, $maxZoom));
    }
}
