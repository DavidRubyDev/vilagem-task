<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use DateTimeImmutable;
use ParcelMap\Application\Clock\Clock;

final readonly class ClockUsingFixedTime implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
