<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\InvalidCoverage;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\MunicipalityNotCovered;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CoverageTest extends TestCase
{
    #[Test]
    public function it_rejects_having_no_areas(): void
    {
        $this->expectException(InvalidCoverage::class);

        new Coverage();
    }

    #[Test]
    public function it_rejects_a_duplicate_area_code(): void
    {
        $this->expectException(InvalidCoverage::class);
        $this->expectExceptionMessage('659541');

        new Coverage(self::area(659541, 572659), self::area(659541, 572659));
    }

    #[Test]
    public function it_keeps_the_areas_in_the_given_order(): void
    {
        $first = self::area(740225, 572659);
        $second = self::area(659541, 572659);

        self::assertSame([$first, $second], new Coverage($first, $second)->areas());
    }

    #[Test]
    public function it_lists_each_municipality_once_in_order_of_first_appearance(): void
    {
        $coverage = new Coverage(
            self::area(659541, 572659),
            self::area(111111, 222222),
            self::area(740225, 572659),
        );

        $codes = array_map(static fn(MunicipalityCode $code): int => $code->value, $coverage->municipalities());

        self::assertSame([572659, 222222], $codes);
    }

    #[Test]
    public function it_sorts_the_areas_by_code_when_created_sorted_by_code(): void
    {
        $coverage = Coverage::sortedByCode(self::area(740225, 572659), self::area(601462, 572675), self::area(659541, 572659));

        self::assertSame(
            [601462, 659541, 740225],
            array_map(static fn(CadastralArea $area): int => $area->code->value, $coverage->areas()),
        );
    }

    #[Test]
    public function it_rejects_a_duplicate_code_when_created_sorted_by_code(): void
    {
        $this->expectException(InvalidCoverage::class);

        Coverage::sortedByCode(self::area(659541, 572659), self::area(659541, 572659));
    }

    #[Test]
    public function it_rejects_having_no_areas_when_created_sorted_by_code(): void
    {
        $this->expectException(InvalidCoverage::class);

        Coverage::sortedByCode();
    }

    #[Test]
    public function it_lists_only_the_areas_of_a_municipality_in_coverage_order(): void
    {
        $coverage = new Coverage(
            self::area(740225, 572659),
            self::area(111111, 222222),
            self::area(659541, 572659),
        );

        $codes = array_map(
            static fn(CadastralArea $area): int => $area->code->value,
            $coverage->areasOf(new MunicipalityCode(572659)),
        );

        self::assertSame([740225, 659541], $codes);
    }

    #[Test]
    public function it_rejects_the_areas_of_a_municipality_it_does_not_cover(): void
    {
        $coverage = new Coverage(self::area(659541, 572659));

        $this->expectException(MunicipalityNotCovered::class);
        $this->expectExceptionMessage('222222');

        $coverage->areasOf(new MunicipalityCode(222222));
    }

    private static function area(int $code, int $municipality): CadastralArea
    {
        return new CadastralArea(new CadastralAreaCode($code), 'Area ' . $code, new MunicipalityCode($municipality));
    }
}
