<?php

declare(strict_types=1);

namespace ParcelMap\Application\Parcel;

use RuntimeException;

final class ParcelDetailNotFound extends RuntimeException
{
    public static function noDataImported(): self
    {
        return new self('No parcel data has been imported.');
    }

    public static function unknownVersion(string $version): self
    {
        return new self(sprintf('There is no parcel data with the version "%s".', $version));
    }

    public static function unknownParcel(int $id): self
    {
        return new self(sprintf('There is no parcel with the id %d.', $id));
    }

    public static function detailsNotImported(string $version): self
    {
        return new self(sprintf('The parcel details of the version "%s" have not been imported.', $version));
    }
}
