<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Clock;

use DateTimeImmutable;
use DateTimeZone;
use ParcelMap\Application\Clock\Clock;

final readonly class ClockUsingSystemTime implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
