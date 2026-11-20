<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class SourceDownloadFailed extends ImportFailed
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
