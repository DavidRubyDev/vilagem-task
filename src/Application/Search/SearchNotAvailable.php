<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

use RuntimeException;

final class SearchNotAvailable extends RuntimeException
{
    public static function noDataImported(): self
    {
        return new self('No parcel data has been imported.');
    }

    public static function unknownVersion(string $version): self
    {
        return new self(sprintf('There is no parcel data with the version "%s".', $version));
    }

    public static function indexNotPublished(string $version): self
    {
        return new self(sprintf('The search index of the version "%s" has not been imported.', $version));
    }
}
