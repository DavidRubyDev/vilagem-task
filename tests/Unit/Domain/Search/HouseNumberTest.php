<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Search\HouseNumber;
use ParcelMap\Domain\Search\HouseNumberKind;
use ParcelMap\Domain\Search\InvalidHouseNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HouseNumberTest extends TestCase
{
    #[Test]
    #[DataProvider('written')]
    public function it_is_written_in_czech_address_notation(HouseNumber $number, string $text): void
    {
        self::assertSame($text, $number->toString());
    }

    /**
     * @return array<string, array{HouseNumber, string}>
     */
    public static function written(): array
    {
        return [
            'descriptive' => [new HouseNumber(HouseNumberKind::Descriptive, 624, null, null), '624'],
            'with an orientation number' => [new HouseNumber(HouseNumberKind::Descriptive, 624, 3, null), '624/3'],
            'with an orientation letter' => [new HouseNumber(HouseNumberKind::Descriptive, 624, 3, 'a'), '624/3a'],
            'registration' => [new HouseNumber(HouseNumberKind::Registration, 12, null, null), 'č. ev. 12'],
            'registration with an orientation number' => [new HouseNumber(HouseNumberKind::Registration, 12, 3, null), 'č. ev. 12/3'],
        ];
    }

    #[Test]
    public function it_knows_both_kinds_by_their_code_of_the_building_type(): void
    {
        self::assertSame(HouseNumberKind::Descriptive, HouseNumberKind::fromCode(1));
        self::assertSame(HouseNumberKind::Registration, HouseNumberKind::fromCode(2));
    }

    #[Test]
    public function it_rejects_a_building_type_that_has_no_house_number(): void
    {
        $this->expectException(InvalidHouseNumber::class);
        $this->expectExceptionMessage('3');

        HouseNumberKind::fromCode(3);
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_an_invalid_number(int $number, ?int $orientation, ?string $letter): void
    {
        $this->expectException(InvalidHouseNumber::class);

        new HouseNumber(HouseNumberKind::Descriptive, $number, $orientation, $letter);
    }

    /**
     * @return array<string, array{int, ?int, ?string}>
     */
    public static function invalid(): array
    {
        return [
            'zero number' => [0, null, null],
            'negative number' => [-1, null, null],
            'zero orientation number' => [5, 0, null],
            'two letters' => [5, 1, 'ab'],
            'a digit as letter' => [5, 1, '1'],
            'an empty letter' => [5, 1, ''],
            'a czech letter' => [5, 1, 'č'],
            'a letter without an orientation number' => [5, null, 'a'],
        ];
    }

    #[Test]
    public function it_accepts_an_upper_case_letter(): void
    {
        self::assertSame('5/1A', new HouseNumber(HouseNumberKind::Descriptive, 5, 1, 'A')->toString());
    }

    #[Test]
    public function it_is_equal_to_a_number_with_the_same_values(): void
    {
        $number = new HouseNumber(HouseNumberKind::Descriptive, 624, 3, 'a');

        self::assertTrue($number->equals(new HouseNumber(HouseNumberKind::Descriptive, 624, 3, 'a')));
        self::assertFalse($number->equals(new HouseNumber(HouseNumberKind::Registration, 624, 3, 'a')));
        self::assertFalse($number->equals(new HouseNumber(HouseNumberKind::Descriptive, 624, 3, null)));
        self::assertFalse($number->equals(new HouseNumber(HouseNumberKind::Descriptive, 625, 3, 'a')));
    }
}
