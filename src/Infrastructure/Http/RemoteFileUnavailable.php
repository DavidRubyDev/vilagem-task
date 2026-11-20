<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Http;

use RuntimeException;

final class RemoteFileUnavailable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
