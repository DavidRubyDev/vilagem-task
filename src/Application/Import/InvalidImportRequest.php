<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;

final class InvalidImportRequest extends InvalidArgumentException
{
    public static function invalidExportDate(string $given, string $reason): self
    {
        return new self(sprintf('"%s" cannot be used as an export date: %s', $given, $reason));
    }
}
