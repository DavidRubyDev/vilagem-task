<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

use InvalidArgumentException;

final class InvalidSearchRequest extends InvalidArgumentException
{
    public static function invalidQuery(string $reason): self
    {
        return new self('Invalid search text: ' . $reason);
    }

    public static function invalidNear(string $reason): self
    {
        return new self('Invalid near position: ' . $reason);
    }
}
