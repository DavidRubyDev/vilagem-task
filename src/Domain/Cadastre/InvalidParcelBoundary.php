<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidParcelBoundary extends InvalidArgumentException
{
    public static function noRings(): self
    {
        return new self('A parcel boundary needs at least one ring.');
    }

    public static function ringTooShort(int $ring): self
    {
        return new self(sprintf('Boundary ring %d needs at least 4 positions.', $ring));
    }

    public static function ringNotClosed(int $ring): self
    {
        return new self(sprintf('Boundary ring %d is not closed: its first and last position differ.', $ring));
    }
}
