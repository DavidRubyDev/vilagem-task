<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\Area;
use ParcelMap\Domain\Cadastre\InvalidArea;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AreaTest extends TestCase
{
    #[Test]
    public function it_keeps_the_square_metres(): void
    {
        self::assertSame(455, new Area(455)->squareMetres);
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_an_area_below_one_square_metre(int $squareMetres): void
    {
        $this->expectException(InvalidArea::class);

        new Area($squareMetres);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalid(): array
    {
        return ['zero' => [0], 'negative' => [-5]];
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        self::assertTrue(new Area(5)->equals(new Area(5)));
        self::assertFalse(new Area(5)->equals(new Area(6)));
    }
}
