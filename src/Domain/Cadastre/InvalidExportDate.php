<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use InvalidArgumentException;

final class InvalidExportDate extends InvalidArgumentException
{
    public static function notADate(string $date): self
    {
        return new self(sprintf('"%s" is not a date in the format YYYY-MM-DD.', $date));
    }

    public static function notLastDayOfMonth(string $date): self
    {
        return new self(sprintf('%s is not the last day of a month; monthly exports are dated on the last day.', $date));
    }
}
