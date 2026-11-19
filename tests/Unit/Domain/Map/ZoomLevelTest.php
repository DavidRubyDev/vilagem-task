<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidZoomLevel;
use ParcelMap\Domain\Map\ZoomLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZoomLevelTest extends TestCase
{
    #[Test]
    #[DataProvider('valid')]
    public function it_accepts_zoom_levels_from_0_to_24(float $value): void
    {
        self::assertSame($value, new ZoomLevel($value)->value);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function valid(): array
    {
        return ['zero' => [0.0], 'fractional' => [12.5], 'maximum' => [24.0]];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_zoom_levels_out_of_range_or_not_finite(float $value): void
    {
        $this->expectException(InvalidZoomLevel::class);

        new ZoomLevel($value);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function invalid(): array
    {
        return ['below' => [-0.1], 'above' => [24.1], 'nan' => [NAN], 'inf' => [INF]];
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        self::assertTrue(new ZoomLevel(12.0)->equals(new ZoomLevel(12.0)));
        self::assertFalse(new ZoomLevel(12.0)->equals(new ZoomLevel(13.0)));
    }
}
