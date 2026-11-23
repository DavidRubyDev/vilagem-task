<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidParcelKind extends InvalidArgumentException
{
    public static function unknownCode(int $code): self
    {
        return new self(sprintf('Unknown parcel numbering code %d; expected 1 (building) or 2 (land).', $code));
    }
}
