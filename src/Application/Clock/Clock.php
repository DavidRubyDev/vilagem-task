<?php

declare(strict_types=1);

namespace ParcelMap\Application\Clock;

use DateTimeImmutable;

interface Clock
{
    /** The current time in UTC. */
    public function now(): DateTimeImmutable;
}
