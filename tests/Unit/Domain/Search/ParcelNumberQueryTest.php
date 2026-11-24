<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Search\InvalidParcelNumberQuery;
use ParcelMap\Domain\Search\ParcelNumberQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelNumberQueryTest extends TestCase
{
    #[Test]
    public function it_accepts_the_largest_numbers_it_can_search(): void
    {
        $query = new ParcelNumberQuery(null, 99999, 9999);

        self::assertSame(99999, $query->baseNumber);
        self::assertSame(9999, $query->subdivision);
    }

    #[Test]
    public function it_rejects_a_base_number_outside_1_to_99999(): void
    {
        foreach ([0, 100000] as $baseNumber) {
            try {
                new ParcelNumberQuery(null, $baseNumber, null);
                self::fail('Expected InvalidParcelNumberQuery for ' . $baseNumber);
            } catch (InvalidParcelNumberQuery $exception) {
                self::assertStringContainsString((string) $baseNumber, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function it_rejects_a_subdivision_outside_1_to_9999(): void
    {
        foreach ([0, 10000] as $subdivision) {
            try {
                new ParcelNumberQuery(null, 80, $subdivision);
                self::fail('Expected InvalidParcelNumberQuery for ' . $subdivision);
            } catch (InvalidParcelNumberQuery $exception) {
                self::assertStringContainsString((string) $subdivision, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function it_matches_both_kinds_when_it_has_none(): void
    {
        $query = new ParcelNumberQuery(null, 72, null);

        self::assertTrue($query->matches(new ParcelNumber(ParcelKind::Building, 72, null)));
        self::assertTrue($query->matches(new ParcelNumber(ParcelKind::Land, 72, null)));
    }

    #[Test]
    public function it_matches_only_its_kind_when_it_has_one(): void
    {
        $query = new ParcelNumberQuery(ParcelKind::Building, 72, null);

        self::assertTrue($query->matches(new ParcelNumber(ParcelKind::Building, 72, null)));
        self::assertFalse($query->matches(new ParcelNumber(ParcelKind::Land, 72, null)));
    }

    #[Test]
    public function it_matches_the_base_number_and_the_subdivision_exactly(): void
    {
        $withoutSubdivision = new ParcelNumberQuery(null, 80, null);
        $withSubdivision = new ParcelNumberQuery(null, 80, 2);

        self::assertFalse($withoutSubdivision->matches(new ParcelNumber(ParcelKind::Land, 80, 2)));
        self::assertTrue($withoutSubdivision->matches(new ParcelNumber(ParcelKind::Land, 80, null)));
        self::assertTrue($withSubdivision->matches(new ParcelNumber(ParcelKind::Land, 80, 2)));
        self::assertFalse($withSubdivision->matches(new ParcelNumber(ParcelKind::Land, 80, 3)));
        self::assertFalse($withSubdivision->matches(new ParcelNumber(ParcelKind::Land, 81, 2)));
    }

    #[Test]
    public function it_is_equal_to_a_query_with_the_same_values(): void
    {
        self::assertTrue(new ParcelNumberQuery(null, 80, 2)->equals(new ParcelNumberQuery(null, 80, 2)));
        self::assertFalse(new ParcelNumberQuery(null, 80, 2)->equals(new ParcelNumberQuery(ParcelKind::Land, 80, 2)));
        self::assertFalse(new ParcelNumberQuery(null, 80, 2)->equals(new ParcelNumberQuery(null, 80, null)));
    }
}
