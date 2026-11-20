<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Http;

use RuntimeException;

final class RemoteFileNotFound extends RuntimeException
{
    public static function at(string $url): self
    {
        return new self(sprintf('%s does not exist (HTTP 404).', $url));
    }
}
