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
