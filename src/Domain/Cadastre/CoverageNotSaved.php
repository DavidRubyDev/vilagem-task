<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use RuntimeException;

final class CoverageNotSaved extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self('The coverage could not be saved: ' . $reason);
    }
}
