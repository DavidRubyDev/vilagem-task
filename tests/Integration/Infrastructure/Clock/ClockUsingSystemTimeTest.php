<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Clock;

use ParcelMap\Infrastructure\Clock\ClockUsingSystemTime;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClockUsingSystemTimeTest extends TestCase
{
    #[Test]
    public function it_returns_the_current_time_in_utc(): void
    {
        $before = time();

        $now = new ClockUsingSystemTime()->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
