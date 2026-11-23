<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class CadastralAreaNotCovered extends InvalidArgumentException
{
    public static function code(int $area): self
    {
        return new self(sprintf('The cadastral area %d is not covered.', $area));
    }
}
