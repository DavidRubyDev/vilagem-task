<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class TilesetOverBudget extends ImportFailed
{
    public static function largestTile(int $zoom, int $x, int $y, int $bytes, int $maxBytes): self
    {
        return new self(sprintf(
            'Tile %d/%d/%d has %d bytes, over the budget of %d bytes; the tileset was not published.',
            $zoom,
            $x,
            $y,
            $bytes,
            $maxBytes,
        ));
    }
}
