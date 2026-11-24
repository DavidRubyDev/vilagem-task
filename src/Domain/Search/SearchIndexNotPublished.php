<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use RuntimeException;

final class SearchIndexNotPublished extends RuntimeException
{
    public static function forVersion(string $version): self
    {
        return new self(sprintf('There is no search index for the version "%s". Run `make data`.', $version));
    }
}
