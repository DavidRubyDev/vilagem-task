<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class SearchIndexBuildFailed extends ImportFailed
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function invalidParcel(string $path, int $line, string $reason): self
    {
        return new self(sprintf('%s line %d: %s', $path, $line, $reason));
    }

    public static function invalidPlace(string $path, string $kind, int $code, string $reason): self
    {
        return new self(sprintf('%s: %s %d: %s', $path, $kind, $code, $reason));
    }
}
