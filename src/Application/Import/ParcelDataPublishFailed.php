<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class ParcelDataPublishFailed extends ImportFailed
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
