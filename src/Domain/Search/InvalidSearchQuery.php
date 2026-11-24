<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use InvalidArgumentException;

final class InvalidSearchQuery extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('The search text is empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf('The search text has %d characters, at most 100 are allowed.', $length));
    }
}
