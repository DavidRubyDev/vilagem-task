<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Import;

use InvalidArgumentException;
use OutOfBoundsException;
use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConvertedParcelsTest extends TestCase
{
    #[Test]
    public function it_counts_the_parcels_per_area_and_in_total(): void
    {
        $converted = $this->converted(self::areaCount(659541, 22), self::areaCount(740225, 3));

        self::assertSame(22, $converted->parcelCountOf(new CadastralAreaCode(659541)));
        self::assertSame(3, $converted->parcelCountOf(new CadastralAreaCode(740225)));
        self::assertSame(25, $converted->totalParcelCount());
        self::assertSame(572659, $converted->municipality->value);
        self::assertSame('/work/572659.geojsonl', $converted->path);
    }

    #[Test]
    public function it_rejects_having_no_area_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->converted();
    }

    #[Test]
    public function it_rejects_an_area_listed_twice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('659541');

        $this->converted(self::areaCount(659541, 22), self::areaCount(659541, 3));
    }

    #[Test]
    public function it_rejects_the_count_of_an_area_it_does_not_hold(): void
    {
        $converted = $this->converted(self::areaCount(659541, 22));

        $this->expectException(OutOfBoundsException::class);

        $converted->parcelCountOf(new CadastralAreaCode(740225));
    }

    private function converted(AreaParcelCount ...$counts): ConvertedParcels
    {
        return new ConvertedParcels(new MunicipalityCode(572659), '/work/572659.geojsonl', array_values($counts));
    }

    private static function areaCount(int $area, int $parcels): AreaParcelCount
    {
        return new AreaParcelCount(new CadastralAreaCode($area), $parcels);
    }
}
