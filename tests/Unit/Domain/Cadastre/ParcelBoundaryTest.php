<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Map\Coordinates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelBoundaryTest extends TestCase
{
    #[Test]
    public function it_rejects_no_rings(): void
    {
        $this->expectException(InvalidParcelBoundary::class);

        ParcelBoundary::fromRings([]);
    }

    #[Test]
    public function it_rejects_a_ring_with_fewer_than_four_positions(): void
    {
        $this->expectException(InvalidParcelBoundary::class);
        $this->expectExceptionMessage('ring 0');

        ParcelBoundary::fromRings([[self::at(1, 1), self::at(2, 1), self::at(1, 1)]]);
    }

    #[Test]
    public function it_rejects_an_open_ring(): void
    {
        $this->expectException(InvalidParcelBoundary::class);
        $this->expectExceptionMessage('ring 1');

        ParcelBoundary::fromRings([self::square(), [self::at(1, 1), self::at(2, 1), self::at(2, 2), self::at(1, 2)]]);
    }

    #[Test]
    public function it_keeps_the_rings_in_order(): void
    {
        $outer = self::square();
        $hole = [self::at(1.2, 1.2), self::at(1.4, 1.2), self::at(1.4, 1.4), self::at(1.2, 1.2)];

        $boundary = ParcelBoundary::fromRings([$outer, $hole]);

        self::assertSame([$outer, $hole], $boundary->rings());
    }

    #[Test]
    public function it_has_the_bounds_of_its_outer_ring_and_ignores_holes(): void
    {
        $outer = [self::at(15.2, 50.1), self::at(15.9, 50.3), self::at(15.6, 50.8), self::at(15.4, 50.5), self::at(15.2, 50.1)];
        // A hole outside the outer ring's extent must not widen the bounds.
        $hole = [self::at(14.0, 49.0), self::at(14.5, 49.0), self::at(14.5, 49.5), self::at(14.0, 49.0)];

        $bounds = ParcelBoundary::fromRings([$outer, $hole])->bounds();

        self::assertSame(15.2, $bounds->southWest->longitude);
        self::assertSame(50.1, $bounds->southWest->latitude);
        self::assertSame(15.9, $bounds->northEast->longitude);
        self::assertSame(50.8, $bounds->northEast->latitude);
    }

    #[Test]
    public function it_equals_a_boundary_built_separately_from_the_same_rings(): void
    {
        self::assertTrue(self::withHole()->equals(self::withHole()));
    }

    #[Test]
    public function it_differs_from_a_boundary_in_which_a_position_differs(): void
    {
        $hole = [self::at(1.2, 1.2), self::at(1.4, 1.2), self::at(1.4, 1.5), self::at(1.2, 1.2)];

        self::assertFalse(self::withHole()->equals(ParcelBoundary::fromRings([self::square(), $hole])));
    }

    #[Test]
    public function it_differs_from_a_boundary_with_an_extra_hole(): void
    {
        $other = [self::at(1.6, 1.6), self::at(1.8, 1.6), self::at(1.8, 1.8), self::at(1.6, 1.6)];

        self::assertFalse(self::withHole()->equals(ParcelBoundary::fromRings([self::square(), self::hole(), $other])));
        self::assertFalse(ParcelBoundary::fromRings([self::square(), self::hole(), $other])->equals(self::withHole()));
    }

    #[Test]
    public function it_differs_from_a_boundary_whose_ring_has_more_positions_but_starts_with_the_same_ones(): void
    {
        $longer = [self::at(1, 1), self::at(2, 1), self::at(2, 2), self::at(1.5, 2.5), self::at(1, 1)];

        self::assertFalse(
            ParcelBoundary::fromRings([[self::at(1, 1), self::at(2, 1), self::at(2, 2), self::at(1, 1)]])
                ->equals(ParcelBoundary::fromRings([$longer])),
        );
        self::assertFalse(
            ParcelBoundary::fromRings([$longer])
                ->equals(ParcelBoundary::fromRings([[self::at(1, 1), self::at(2, 1), self::at(2, 2), self::at(1, 1)]])),
        );
    }

    #[Test]
    public function it_differs_from_a_boundary_with_the_same_rings_in_another_order(): void
    {
        self::assertFalse(self::withHole()->equals(ParcelBoundary::fromRings([self::hole(), self::square()])));
    }

    private static function withHole(): ParcelBoundary
    {
        return ParcelBoundary::fromRings([self::square(), self::hole()]);
    }

    /**
     * @return list<Coordinates>
     */
    private static function hole(): array
    {
        return [self::at(1.2, 1.2), self::at(1.4, 1.2), self::at(1.4, 1.4), self::at(1.2, 1.2)];
    }

    /**
     * @return list<Coordinates>
     */
    private static function square(): array
    {
        return [self::at(1, 1), self::at(2, 1), self::at(2, 2), self::at(1, 1)];
    }

    private static function at(float $longitude, float $latitude): Coordinates
    {
        return new Coordinates($longitude, $latitude);
    }
}
