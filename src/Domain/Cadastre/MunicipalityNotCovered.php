<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class MunicipalityNotCovered extends InvalidArgumentException
{
    public static function code(int $municipality): self
    {
        return new self(sprintf('The municipality %d has no covered cadastral area.', $municipality));
    }
}
