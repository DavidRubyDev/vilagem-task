<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Search\Address;
use ParcelMap\Domain\Search\HouseNumber;
use ParcelMap\Domain\Search\HouseNumberKind;
use ParcelMap\Domain\Search\InvalidPlace;
use ParcelMap\Domain\Search\Place;
use ParcelMap\Domain\Search\PlaceKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlaceTest extends TestCase
{
    #[Test]
    public function it_orders_the_kinds_the_way_results_are_ranked(): void
    {
        self::assertSame(
            [PlaceKind::Municipality, PlaceKind::CadastralArea, PlaceKind::MunicipalityPart, PlaceKind::Street, PlaceKind::Address],
            [...PlaceKind::cases()],
        );
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn(PlaceKind $kind): int => $kind->value, PlaceKind::cases()));
    }

    #[Test]
    public function it_is_built_from_an_address(): void
    {
        $address = new Address(
            'Valdštejnovo náměstí',
            'Staré Město',
            'Jičín',
            new HouseNumber(HouseNumberKind::Descriptive, 58, null, null),
            new Coordinates(15.351066, 50.4373768),
        );

        $place = Place::fromAddress(25344951, new MunicipalityCode(572659), $address, new ParcelId(1753295604));

        self::assertSame(PlaceKind::Address, $place->kind);
        self::assertSame(25344951, $place->code);
        self::assertSame(572659, $place->municipality->value);
        self::assertSame('Valdštejnovo náměstí 58', $place->name);
        self::assertSame('Staré Město, Jičín', $place->context);
        self::assertTrue($place->center->equals(new Coordinates(15.351066, 50.4373768)));
        self::assertNull($place->bounds);
        self::assertSame(1753295604, $place->parcel?->value);
    }

    #[Test]
    public function it_has_a_key_of_its_normalised_name(): void
    {
        self::assertSame('valdstejnovo namesti 58', self::place(PlaceKind::Address, 1, 'Valdštejnovo náměstí 58')->key());
    }

    #[Test]
    public function it_accepts_bounds_around_its_centre_including_the_edge(): void
    {
        $bounds = new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.2, 50.2));

        self::assertSame($bounds, new Place(PlaceKind::Street, 1, new MunicipalityCode(572659), 'Husova', 'Jičín', new Coordinates(15.1, 50.1), $bounds, null)->bounds);
        self::assertSame($bounds, new Place(PlaceKind::Street, 1, new MunicipalityCode(572659), 'Husova', 'Jičín', new Coordinates(15.0, 50.2), $bounds, null)->bounds);
    }

    #[Test]
    public function it_rejects_a_code_that_is_not_positive(): void
    {
        $this->expectException(InvalidPlace::class);

        self::place(PlaceKind::Street, 0, 'Husova');
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $this->expectException(InvalidPlace::class);

        self::place(PlaceKind::Street, 5, '   ');
    }

    #[Test]
    public function it_rejects_a_parcel_on_a_place_that_is_not_an_address(): void
    {
        $this->expectException(InvalidPlace::class);

        new Place(PlaceKind::Street, 5, new MunicipalityCode(572659), 'Husova', 'Jičín', new Coordinates(15.1, 50.1), null, new ParcelId(1));
    }

    #[Test]
    public function it_rejects_a_centre_outside_its_bounds(): void
    {
        $bounds = new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.2, 50.2));

        $this->expectException(InvalidPlace::class);

        new Place(PlaceKind::Street, 5, new MunicipalityCode(572659), 'Husova', 'Jičín', new Coordinates(15.3, 50.1), $bounds, null);
    }

    #[Test]
    public function it_is_the_same_place_for_the_same_kind_and_code(): void
    {
        $place = self::place(PlaceKind::Street, 5, 'Husova');

        self::assertTrue($place->equals(self::place(PlaceKind::Street, 5, 'Another name')));
        self::assertFalse($place->equals(self::place(PlaceKind::Street, 6, 'Husova')));
        self::assertFalse($place->equals(self::place(PlaceKind::CadastralArea, 5, 'Husova')));
    }

    private static function place(PlaceKind $kind, int $code, string $name): Place
    {
        return new Place($kind, $code, new MunicipalityCode(572659), $name, 'Jičín', new Coordinates(15.1, 50.1), null, null);
    }
}
