<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidTileCoordinate extends InvalidArgumentException
{
    public static function outOfRange(int $zoom, int $x, int $y): self
    {
        return new self(sprintf('The tile %d/%d/%d is outside the tile grid (zoom 0 to 24, x and y below 2^zoom).', $zoom, $x, $y));
    }
}
