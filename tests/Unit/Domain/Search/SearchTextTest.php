<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Search\SearchText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchTextTest extends TestCase
{
    #[Test]
    #[DataProvider('normalisations')]
    public function it_lower_cases_and_removes_diacritics(string $text, string $normalized): void
    {
        self::assertSame($normalized, SearchText::of($text)->normalized);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalisations(): array
    {
        return [
            'czech letters' => ['Jičín', 'jicin'],
            'capital with caron' => ['ŘEPA', 'repa'],
            'umlaut' => ['Müller', 'muller'],
            'slovak letters' => ['Ľubovňa Ĺ Ŕ Ô', 'lubovna l r o'],
            'punctuation becomes one space' => ['17. listopadu', '17 listopadu'],
            'runs of separators collapse' => ["  Nábř.  Kpt. -  Jaroše\t", 'nabr kpt jarose'],
            'slash is kept' => ['80/2', '80/2'],
            'other characters are separators' => ['a_b+c', 'a b c'],
            'empty' => ['', ''],
            'only separators' => [" .\t- ", ''],
        ];
    }

    #[Test]
    public function it_splits_the_text_into_tokens(): void
    {
        self::assertSame(['st', '80/2'], SearchText::of('st. 80/2')->tokens());
    }

    #[Test]
    public function it_has_no_tokens_for_an_empty_text(): void
    {
        self::assertSame([], SearchText::of(' . ')->tokens());
    }

    #[Test]
    public function it_splits_terms_at_the_slash(): void
    {
        self::assertSame(['husova', '624', '3a'], SearchText::of('Husova 624/3a')->terms());
    }

    #[Test]
    public function it_drops_empty_parts_and_single_letters_from_the_terms(): void
    {
        self::assertSame(['popovice', 'jicina'], SearchText::of('Popovice u Jičína')->terms());
        self::assertSame(['80', '2'], SearchText::of('/80//2/')->terms());
    }

    #[Test]
    public function it_keeps_single_digits_in_the_terms(): void
    {
        self::assertSame(['husova', '7'], SearchText::of('Husova 7')->terms());
    }

    #[Test]
    #[DataProvider('keys')]
    public function it_joins_the_terms_into_a_key(string $text, string $key): void
    {
        self::assertSame($key, SearchText::of($text)->key());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function keys(): array
    {
        return [
            'square' => ['Valdštejnovo náměstí 58', 'valdstejnovo namesti 58'],
            'registration number' => ['Husova č. ev. 12', 'husova ev 12'],
            'orientation number' => ['Husova 624/3a', 'husova 624 3a'],
        ];
    }

    #[Test]
    public function it_knows_when_it_is_empty(): void
    {
        self::assertTrue(SearchText::of(" \t")->isEmpty());
        self::assertFalse(SearchText::of('a')->isEmpty());
    }

    #[Test]
    public function it_is_equal_to_a_text_with_the_same_normalisation(): void
    {
        self::assertTrue(SearchText::of('Jičín')->equals(SearchText::of('JICIN!')));
        self::assertFalse(SearchText::of('Jičín')->equals(SearchText::of('Jihlava')));
    }
}
