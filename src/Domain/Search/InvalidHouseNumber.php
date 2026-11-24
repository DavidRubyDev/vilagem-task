<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use InvalidArgumentException;

final class InvalidHouseNumber extends InvalidArgumentException
{
    public static function unknownKind(int $code): self
    {
        return new self(sprintf('The building type %d has no house number (1 descriptive, 2 registration).', $code));
    }

    public static function notPositive(int $number): self
    {
        return new self(sprintf('A house number must be at least 1, %d given.', $number));
    }

    public static function orientationNotPositive(int $number): self
    {
        return new self(sprintf('An orientation number must be at least 1, %d given.', $number));
    }

    public static function invalidLetter(string $letter): self
    {
        return new self(sprintf('An orientation letter must be one letter a-z, "%s" given.', $letter));
    }

    public static function letterWithoutOrientation(): self
    {
        return new self('An orientation letter needs an orientation number.');
    }
}
