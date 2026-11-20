<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class TileBuildFailed extends ImportFailed
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
