<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

use RuntimeException;

final class Gzip
{
    public static function encode(string $data): string
    {
        return gzencode($data) ?: throw new RuntimeException('Cannot gzip the data.');
    }
}
