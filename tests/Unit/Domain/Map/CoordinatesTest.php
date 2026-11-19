<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\InvalidCoordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CoordinatesTest extends TestCase
{
    #[Test]
    public function it_accepts_jicin(): void
    {
        $coordinates = new Coordinates(longitude: 15.351653, latitude: 50.437045);

        self::assertSame(15.351653, $coordinates->longitude);
        self::assertSame(50.437045, $coordinates->latitude);
    }

    #[Test]
    public function it_accepts_the_boundary_values(): void
    {
        self::assertSame(-180.0, new Coordinates(-180.0, -90.0)->longitude);
        self::assertSame(90.0, new Coordinates(180.0, 90.0)->latitude);
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function outOfRange(): array
    {
        return [
            'longitude above' => [180.000001, 0.0],
            'longitude below' => [-180.000001, 0.0],
            'latitude above' => [0.0, 90.000001],
            'latitude below' => [0.0, -90.000001],
        ];
    }

    #[Test]
    #[DataProvider('outOfRange')]
    public function it_rejects_values_out_of_range(float $longitude, float $latitude): void
    {
        $this->expectException(InvalidCoordinates::class);

        new Coordinates($longitude, $latitude);
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function notFinite(): array
    {
        return [
            'nan longitude' => [NAN, 0.0],
            'inf longitude' => [INF, 0.0],
            'nan latitude' => [0.0, NAN],
            'inf latitude' => [0.0, -INF],
        ];
    }

    #[Test]
    #[DataProvider('notFinite')]
    public function it_rejects_values_that_are_not_finite(float $longitude, float $latitude): void
    {
        $this->expectException(InvalidCoordinates::class);

        new Coordinates($longitude, $latitude);
    }

    #[Test]
    public function it_equals_an_instance_with_the_same_values_only(): void
    {
        $coordinates = new Coordinates(15.35, 50.43);

        self::assertTrue($coordinates->equals(new Coordinates(15.35, 50.43)));
        self::assertFalse($coordinates->equals(new Coordinates(15.35, 50.44)));
        self::assertFalse($coordinates->equals(new Coordinates(15.36, 50.43)));
    }
}
