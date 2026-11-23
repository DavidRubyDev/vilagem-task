<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidCoverage extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('The coverage must contain at least one cadastral area.');
    }

    public static function duplicateArea(int $code): self
    {
        return new self(sprintf('The cadastral area %d is listed more than once.', $code));
    }

    public static function inconsistentMunicipality(int $code): self
    {
        return new self(sprintf('The areas of municipality %d do not agree on its name.', $code));
    }
}
