<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

final class DistrictNotFound extends CoverageGenerationFailed
{
    public static function for(int $district, string $date): self
    {
        return new self(sprintf('District %d has no cadastral areas in the RÚIAN export of %s.', $district, $date));
    }
}
