<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\InvalidBounds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BoundsTest extends TestCase
{
    #[Test]
    public function it_keeps_the_south_west_and_north_east_corners(): void
    {
        $bounds = new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5));

        self::assertSame(15.0, $bounds->southWest->longitude);
        self::assertSame(50.0, $bounds->southWest->latitude);
        self::assertSame(15.5, $bounds->northEast->longitude);
        self::assertSame(50.5, $bounds->northEast->latitude);
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_equal_or_swapped_corners(Coordinates $southWest, Coordinates $northEast): void
    {
        $this->expectException(InvalidBounds::class);

        new Bounds($southWest, $northEast);
    }

    /**
     * @return array<string, array{Coordinates, Coordinates}>
     */
    public static function invalid(): array
    {
        return [
            'equal' => [new Coordinates(15.0, 50.0), new Coordinates(15.0, 50.0)],
            'swapped longitude' => [new Coordinates(15.5, 50.0), new Coordinates(15.0, 50.5)],
            'swapped latitude' => [new Coordinates(15.0, 50.5), new Coordinates(15.5, 50.0)],
            'same longitude' => [new Coordinates(15.0, 50.0), new Coordinates(15.0, 50.5)],
            'same latitude' => [new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.0)],
        ];
    }
}
