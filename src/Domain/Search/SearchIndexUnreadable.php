<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use RuntimeException;

final class SearchIndexUnreadable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('The search index cannot be read: ' . $reason);
    }
}
