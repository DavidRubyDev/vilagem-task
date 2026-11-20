<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

final class StructureDownloadFailed extends CoverageGenerationFailed
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
