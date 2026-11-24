<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Search\Address;
use ParcelMap\Domain\Search\HouseNumber;
use ParcelMap\Domain\Search\HouseNumberKind;
use ParcelMap\Domain\Search\InvalidAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    #[Test]
    public function it_is_labelled_with_the_street_and_the_house_number(): void
    {
        $address = self::address('Valdštejnovo náměstí', 'Staré Město', 'Jičín', 58);

        self::assertSame('Valdštejnovo náměstí 58', $address->label());
    }

    #[Test]
    public function it_is_labelled_with_the_part_of_the_municipality_without_a_street(): void
    {
        self::assertSame('Robousy 239', self::address(null, 'Robousy', 'Jičín', 239)->label());
        self::assertSame(
            'Robousy č. ev. 5',
            new Address(null, 'Robousy', 'Jičín', new HouseNumber(HouseNumberKind::Registration, 5, null, null), self::point())->label(),
        );
    }

    #[Test]
    public function it_has_the_part_before_the_municipality_as_context_when_there_is_a_street(): void
    {
        self::assertSame('Staré Město, Jičín', self::address('Valdštejnovo náměstí', 'Staré Město', 'Jičín', 58)->context());
    }

    #[Test]
    public function it_has_only_the_municipality_as_context_when_the_part_is_named_like_it(): void
    {
        self::assertSame('Jičín', self::address('Husova', 'Jičín', 'Jičín', 1)->context());
        self::assertSame('Nová Paka', self::address('Husova', 'NOVÁ PAKA', 'Nová Paka', 1)->context());
    }

    #[Test]
    public function it_has_only_the_municipality_as_context_without_a_street(): void
    {
        self::assertSame('Jičín', self::address(null, 'Robousy', 'Jičín', 239)->context());
    }

    #[Test]
    #[DataProvider('blankNames')]
    public function it_rejects_a_blank_name(?string $street, string $part, string $municipality, string $field): void
    {
        try {
            new Address($street, $part, $municipality, new HouseNumber(HouseNumberKind::Descriptive, 1, null, null), self::point());
            self::fail('Expected InvalidAddress.');
        } catch (InvalidAddress $exception) {
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{?string, string, string, string}>
     */
    public static function blankNames(): array
    {
        return [
            'blank street' => ['  ', 'Part', 'Municipality', 'street'],
            'empty street' => ['', 'Part', 'Municipality', 'street'],
            'blank part' => ['Street', ' ', 'Municipality', 'municipality part'],
            'blank municipality' => ['Street', 'Part', '', 'municipality'],
        ];
    }

    #[Test]
    public function it_is_equal_to_an_address_with_all_the_same_values(): void
    {
        $address = self::address('Husova', 'Jičín', 'Jičín', 1);

        self::assertTrue($address->equals(self::address('Husova', 'Jičín', 'Jičín', 1)));
        self::assertFalse($address->equals(self::address('Husova', 'Jičín', 'Jičín', 2)));
        self::assertFalse($address->equals(self::address('Tyršova', 'Jičín', 'Jičín', 1)));
        self::assertFalse($address->equals(self::address(null, 'Jičín', 'Jičín', 1)));
        self::assertFalse($address->equals(new Address('Husova', 'Jičín', 'Jičín', new HouseNumber(HouseNumberKind::Descriptive, 1, null, null), new Coordinates(15.0, 50.0))));
    }

    private static function address(?string $street, string $part, string $municipality, int $number): Address
    {
        return new Address($street, $part, $municipality, new HouseNumber(HouseNumberKind::Descriptive, $number, null, null), self::point());
    }

    private static function point(): Coordinates
    {
        return new Coordinates(15.351066, 50.4373768);
    }
}
