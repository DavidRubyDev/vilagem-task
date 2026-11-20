<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use DateTimeImmutable;

/** Date of a monthly RÚIAN export: always the last day of a month (published the day after). */
final readonly class ExportDate
{
    private const string FORMAT = 'Y-m-d';

    private function __construct(private DateTimeImmutable $date) {}

    public static function fromString(string $date): self
    {
        $parsed = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $date);
        if ($parsed === false || $parsed->format(self::FORMAT) !== $date) {
            throw InvalidExportDate::notADate($date);
        }
        if ($parsed->format('t') !== $parsed->format('d')) {
            throw InvalidExportDate::notLastDayOfMonth($date);
        }

        return new self($parsed);
    }

    public static function latestPublishedOn(DateTimeImmutable $now): self
    {
        return new self(self::lastDayOfPreviousMonth($now));
    }

    public function previous(): self
    {
        return new self(self::lastDayOfPreviousMonth($this->date));
    }

    public function year(): int
    {
        return (int) $this->date->format('Y');
    }

    public function toString(): string
    {
        return $this->date->format(self::FORMAT);
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    private static function lastDayOfPreviousMonth(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->modify('first day of this month')->setTime(0, 0)->modify('-1 day');
    }
}
