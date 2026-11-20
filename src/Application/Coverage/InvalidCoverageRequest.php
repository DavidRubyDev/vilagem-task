<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

use InvalidArgumentException;

final class InvalidCoverageRequest extends InvalidArgumentException
{
    public static function invalidDistrict(int $given, string $reason): self
    {
        return new self(sprintf('%d cannot be used as a district code: %s', $given, $reason));
    }

    public static function invalidExportDate(string $given, string $reason): self
    {
        return new self(sprintf('"%s" cannot be used as an export date: %s', $given, $reason));
    }
}
