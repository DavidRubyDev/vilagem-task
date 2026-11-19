<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidTileUrlTemplate extends InvalidArgumentException
{
    public static function notHttps(string $template): self
    {
        return new self(sprintf('Tile URL template must start with https://, "%s" given.', $template));
    }

    public static function missingPlaceholder(string $template, string $placeholder): self
    {
        return new self(sprintf('Tile URL template "%s" must contain %s.', $template, $placeholder));
    }

    public static function notAUrl(string $template): self
    {
        return new self(sprintf('Tile URL template "%s" is not a valid URL.', $template));
    }
}
