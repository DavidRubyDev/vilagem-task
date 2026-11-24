<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Search\InvalidSearchQuery;
use ParcelMap\Domain\Search\ParcelNumberQuery;
use ParcelMap\Domain\Search\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    #[Test]
    #[DataProvider('parcelNumbers')]
    public function it_reads_a_parcel_number(string $text, ?ParcelKind $kind, int $base, ?int $subdivision): void
    {
        $query = SearchQuery::parse($text);

        self::assertTrue($query->searchesParcels());
        self::assertNotNull($query->parcelNumber());
        self::assertTrue($query->parcelNumber()->equals(new ParcelNumberQuery($kind, $base, $subdivision)));
        self::assertSame([], $query->nameTerms());
        self::assertNull($query->cadastralAreaCode());
    }

    /**
     * @return array<string, array{string, ?ParcelKind, int, ?int}>
     */
    public static function parcelNumbers(): array
    {
        return [
            'building' => ['st. 72', ParcelKind::Building, 72, null],
            'any kind' => ['72', null, 72, null],
            'subdivision' => ['80/2', null, 80, 2],
            'building with subdivision without a dot' => ['st 80/2', ParcelKind::Building, 80, 2],
            'upper case st' => ['ST. 72', ParcelKind::Building, 72, null],
            'parcel number as people write it' => ['p. č. 80/2', null, 80, 2],
            'parcela abbreviation' => ['parc. č. 80/2', null, 80, 2],
            'largest numbers' => ['99999/9999', null, 99999, 9999],
        ];
    }

    #[Test]
    public function it_takes_the_words_around_the_number_as_name_terms_in_any_order(): void
    {
        self::assertSame(['robousy'], SearchQuery::parse('867/121 Robousy')->nameTerms());
        self::assertSame(['robousy'], SearchQuery::parse('Robousy 867/121')->nameTerms());
        self::assertSame(['nova', 'paka'], SearchQuery::parse('st. 12 Nová Paka')->nameTerms());
    }

    #[Test]
    public function it_reads_a_six_digit_code_as_the_cadastral_area(): void
    {
        $query = SearchQuery::parse('867/121 740225');

        self::assertSame(740225, $query->cadastralAreaCode()?->value);
        self::assertSame([], $query->nameTerms());
        self::assertTrue($query->searchesParcels());
    }

    #[Test]
    public function it_reads_six_digits_as_an_area_code_and_not_as_a_parcel_number(): void
    {
        $query = SearchQuery::parse('100000');

        self::assertNull($query->parcelNumber());
        self::assertFalse($query->searchesParcels());
        self::assertSame(100000, $query->cadastralAreaCode()?->value);
    }

    #[Test]
    #[DataProvider('notParcelNumbers')]
    public function it_finds_no_parcel_number_in(string $text): void
    {
        $query = SearchQuery::parse($text);

        self::assertNull($query->parcelNumber());
        self::assertFalse($query->searchesParcels());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notParcelNumbers(): array
    {
        return [
            'zero' => ['0'],
            'zero base with subdivision' => ['0/1'],
            'zero subdivision' => ['80/0'],
            'a word' => ['Husova'],
            'st alone' => ['st'],
            'too many digits in the subdivision' => ['80/12345'],
            'three parts' => ['80/2/3'],
            'a number with letters' => ['80a'],
        ];
    }

    #[Test]
    public function it_keeps_a_second_number_as_a_name_term(): void
    {
        $query = SearchQuery::parse('12 13 80/2');

        self::assertSame(12, $query->parcelNumber()?->baseNumber);
        self::assertSame(['13', '80', '2'], $query->nameTerms());
    }

    #[Test]
    public function it_ignores_single_letters_and_pc_and_parc_in_the_name_terms(): void
    {
        self::assertSame(['popovice', 'jicina'], SearchQuery::parse('72 Popovice u Jičína')->nameTerms());
        self::assertSame([], SearchQuery::parse('č. p. pč parc 72')->nameTerms());
    }

    #[Test]
    public function it_keeps_a_word_before_a_number_as_a_name_term_and_st_elsewhere_as_a_word(): void
    {
        self::assertSame(['husova'], SearchQuery::parse('Husova 12')->nameTerms());
        self::assertSame(['st', 'paka'], SearchQuery::parse('st paka 12')->nameTerms());
    }

    #[Test]
    public function it_makes_the_terms_of_letters_and_digits_only(): void
    {
        self::assertSame(['nabr', 'kpt', 'jarose'], SearchQuery::parse("Nábř. Kpt. Jaroše; 5 'x\"%_")->nameTerms());
    }

    #[Test]
    public function it_rejects_an_empty_text(): void
    {
        foreach (['', '   ', "\t\n"] as $text) {
            try {
                SearchQuery::parse($text);
                self::fail('Expected InvalidSearchQuery.');
            } catch (InvalidSearchQuery $exception) {
                self::assertStringContainsString('empty', $exception->getMessage());
            }
        }
    }

    #[Test]
    public function it_rejects_a_text_of_more_than_100_characters_and_accepts_100(): void
    {
        self::assertSame(['a' . str_repeat('b', 99)], SearchQuery::parse('a' . str_repeat('b', 99))->nameTerms());

        try {
            SearchQuery::parse(str_repeat('b', 101));
            self::fail('Expected InvalidSearchQuery.');
        } catch (InvalidSearchQuery $exception) {
            self::assertStringContainsString('101', $exception->getMessage());
        }
    }

    #[Test]
    public function it_counts_characters_not_bytes_and_trims_before_counting(): void
    {
        SearchQuery::parse(str_repeat('č', 100));
        SearchQuery::parse('  ' . str_repeat('b', 100) . '  ');

        $this->expectException(InvalidSearchQuery::class);

        SearchQuery::parse(str_repeat('č', 101));
    }

    #[Test]
    public function it_has_no_parcel_number_and_no_terms_for_only_punctuation(): void
    {
        $query = SearchQuery::parse('...');

        self::assertFalse($query->searchesParcels());
        self::assertSame([], $query->nameTerms());
    }
}
