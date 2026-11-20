<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use DateTimeImmutable;
use DateTimeZone;
use ParcelMap\Domain\Map\InvalidTilesetVersion;
use ParcelMap\Domain\Map\TilesetVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TilesetVersionTest extends TestCase
{
    #[Test]
    public function it_accepts_a_timestamp_like_version(): void
    {
        self::assertSame('20261120093000', new TilesetVersion('20261120093000')->value);
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_a_malformed_version(string $value): void
    {
        $this->expectException(InvalidTilesetVersion::class);

        new TilesetVersion($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return ['empty' => [''], 'too long' => [str_repeat('a', 33)], 'slash' => ['a/b']];
    }

    #[Test]
    public function it_is_built_from_a_time_in_utc(): void
    {
        $time = new DateTimeImmutable('2026-11-20 10:30:00', new DateTimeZone('+01:00'));

        self::assertSame('20261120093000', TilesetVersion::fromTime($time)->value);
    }
}
