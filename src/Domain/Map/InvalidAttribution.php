<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use InvalidArgumentException;

final class InvalidAttribution extends InvalidArgumentException
{
    public static function emptyText(): self
    {
        return new self('Attribution text must not be empty.');
    }

    public static function invalidUrl(string $url): self
    {
        return new self(sprintf('Attribution URL must be a valid https URL, "%s" given.', $url));
    }
}
