<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidCadastralArea extends InvalidArgumentException
{
    public static function emptyName(int $code): self
    {
        return new self(sprintf('The cadastral area %d must have a name.', $code));
    }
}
