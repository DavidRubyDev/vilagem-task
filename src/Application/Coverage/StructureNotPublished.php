<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

final class StructureNotPublished extends CoverageGenerationFailed
{
    public static function for(string $date): self
    {
        return new self(sprintf('The RÚIAN state structure file of %s is not published.', $date));
    }
}
