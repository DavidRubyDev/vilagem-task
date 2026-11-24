<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Search\DistinctPlaces;
use ParcelMap\Domain\Search\Place;
use ParcelMap\Domain\Search\PlaceKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DistinctPlacesTest extends TestCase
{
    #[Test]
    public function it_keeps_the_municipality_and_drops_the_cadastral_area_of_the_same_name(): void
    {
        $places = [self::place(PlaceKind::CadastralArea, 659541, 'Jičín'), self::place(PlaceKind::Municipality, 572659, 'Jičín')];

        self::assertSame([PlaceKind::Municipality], self::kinds(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_keeps_the_cadastral_area_and_drops_the_part_of_the_same_name(): void
    {
        $places = [self::place(PlaceKind::MunicipalityPart, 140228, 'Robousy'), self::place(PlaceKind::CadastralArea, 740225, 'Robousy')];

        self::assertSame([PlaceKind::CadastralArea], self::kinds(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_keeps_the_municipality_when_all_three_kinds_share_a_name(): void
    {
        $places = [
            self::place(PlaceKind::MunicipalityPart, 3, 'Chloumek'),
            self::place(PlaceKind::CadastralArea, 2, 'Chloumek'),
            self::place(PlaceKind::Municipality, 1, 'Chloumek'),
        ];

        self::assertSame([PlaceKind::Municipality], self::kinds(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_compares_the_names_by_their_search_key(): void
    {
        $places = [self::place(PlaceKind::Municipality, 1, 'Nová Paka'), self::place(PlaceKind::CadastralArea, 2, 'NOVA PAKA.')];

        self::assertSame([1], self::codes(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_keeps_a_name_that_occurs_in_two_municipalities_in_both(): void
    {
        $places = [
            self::place(PlaceKind::MunicipalityPart, 1, 'Chloumek', 572659),
            self::place(PlaceKind::MunicipalityPart, 2, 'Chloumek', 535001),
        ];

        self::assertSame([1, 2], self::codes(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_never_drops_streets_and_addresses(): void
    {
        $places = [
            self::place(PlaceKind::Municipality, 1, 'Husova'),
            self::place(PlaceKind::Street, 2, 'Husova'),
            self::place(PlaceKind::Street, 3, 'Husova'),
            self::place(PlaceKind::Address, 4, 'Husova', parcel: true),
        ];

        self::assertSame([1, 2, 3, 4], self::codes(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_keeps_two_places_of_the_same_lowest_kind_and_the_input_order(): void
    {
        $places = [
            self::place(PlaceKind::MunicipalityPart, 9, 'Podhrad'),
            self::place(PlaceKind::Street, 5, 'Husova'),
            self::place(PlaceKind::MunicipalityPart, 7, 'Podhrad'),
            self::place(PlaceKind::CadastralArea, 1, 'Jinde'),
        ];

        self::assertSame([9, 5, 7, 1], self::codes(DistinctPlaces::keep($places)));
    }

    #[Test]
    public function it_returns_nothing_for_nothing(): void
    {
        self::assertSame([], DistinctPlaces::keep([]));
    }

    /**
     * @param list<Place> $places
     * @return list<PlaceKind>
     */
    private static function kinds(array $places): array
    {
        return array_map(static fn(Place $place): PlaceKind => $place->kind, $places);
    }

    /**
     * @param list<Place> $places
     * @return list<int>
     */
    private static function codes(array $places): array
    {
        return array_map(static fn(Place $place): int => $place->code, $places);
    }

    private static function place(PlaceKind $kind, int $code, string $name, int $municipality = 572659, bool $parcel = false): Place
    {
        return new Place(
            $kind,
            $code,
            new MunicipalityCode($municipality),
            $name,
            '',
            new Coordinates(15.1, 50.1),
            null,
            $parcel ? new ParcelId(1) : null,
        );
    }
}
