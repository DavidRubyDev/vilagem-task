<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use RuntimeException;

final class MapPageUnavailable extends RuntimeException
{
    public static function cannotRead(string $path): self
    {
        return new self(sprintf('Cannot read the map page file "%s".', $path));
    }
}
