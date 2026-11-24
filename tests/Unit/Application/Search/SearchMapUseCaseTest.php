<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Search;

use ParcelMap\Application\Search\InvalidSearchRequest;
use ParcelMap\Application\Search\PlaceResultOutput;
use ParcelMap\Application\Search\SearchMapInput;
use ParcelMap\Application\Search\SearchMapUseCase;
use ParcelMap\Application\Search\SearchNotAvailable;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\ParcelTilesetUnreadable;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Search\Address;
use ParcelMap\Domain\Search\HouseNumber;
use ParcelMap\Domain\Search\HouseNumberKind;
use ParcelMap\Domain\Search\ParcelMatch;
use ParcelMap\Domain\Search\Place;
use ParcelMap\Domain\Search\PlaceKind;
use ParcelMap\Domain\Search\SearchIndexUnreadable;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use ParcelMap\Tests\Fake\SearchIndexRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchMapUseCaseTest extends TestCase
{
    private const string VERSION = '20261120093000';

    #[Test]
    public function it_maps_a_found_parcel_to_the_output(): void
    {
        $output = $this->useCase()->execute(new SearchMapInput(self::VERSION, 'st. 72', null, null));

        self::assertSame([], $output->places);
        self::assertCount(1, $output->parcels);
        $parcel = $output->parcels[0];
        self::assertSame(1753295604, $parcel->id);
        self::assertSame('st. 72', $parcel->parcelNumber);
        self::assertSame('building', $parcel->kind);
        self::assertSame(659541, $parcel->cadastralAreaCode);
        self::assertSame('Jičín', $parcel->cadastralAreaName);
        self::assertSame(572659, $parcel->municipalityCode);
        self::assertSame('Jičín', $parcel->municipalityName);
    }

    #[Test]
    public function it_names_a_land_parcel_land(): void
    {
        $output = $this->useCase()->execute(new SearchMapInput(self::VERSION, '867/121', null, null));

        self::assertSame('land', $output->parcels[0]->kind);
        self::assertSame('867/121', $output->parcels[0]->parcelNumber);
    }

    #[Test]
    public function it_asks_the_index_for_at_most_eight_parcels_with_the_near_coordinates(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), self::parcelMatches());

        $this->useCase($index)->execute(new SearchMapInput(self::VERSION, '72', 15.35, 50.43));

        self::assertCount(1, $index->parcelCalls);
        self::assertSame(self::VERSION, $index->parcelCalls[0]['version']);
        self::assertSame(72, $index->parcelCalls[0]['query']->parcelNumber()?->baseNumber);
        self::assertEquals(new Coordinates(15.35, 50.43), $index->parcelCalls[0]['near']);
        self::assertSame(8, $index->parcelCalls[0]['limit']);
    }

    #[Test]
    public function it_asks_without_near_coordinates_when_none_are_given(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), self::parcelMatches());

        $this->useCase($index)->execute(new SearchMapInput(self::VERSION, '72', null, null));

        self::assertNull($index->parcelCalls[0]['near']);
    }

    #[Test]
    public function it_does_not_search_parcels_for_a_text_without_a_parcel_number(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), self::parcelMatches(), self::places());

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'jicin', null, null));

        self::assertSame([], $output->parcels);
        self::assertSame([], $index->parcelCalls);
        self::assertCount(1, $index->placeCalls);
    }

    #[Test]
    public function it_does_not_search_places_for_a_text_that_is_only_a_parcel_number(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), self::parcelMatches(), self::places());

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'st. 72', null, null));

        self::assertSame([], $output->places);
        self::assertSame([], $index->placeCalls);
        self::assertCount(1, $index->parcelCalls);
    }

    #[Test]
    public function it_searches_both_for_a_word_and_a_number(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), self::parcelMatches(), self::places());

        $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'Robousy 72', 15.35, 50.43));

        self::assertCount(1, $index->parcelCalls);
        self::assertCount(1, $index->placeCalls);
        self::assertSame('robousy 72', $index->placeCalls[0]['query']->placeKey());
        self::assertEquals(new Coordinates(15.35, 50.43), $index->placeCalls[0]['near']);
        self::assertSame(8, $index->placeCalls[0]['limit']);
        self::assertSame(self::VERSION, $index->placeCalls[0]['version']);
    }

    #[Test]
    public function it_maps_an_address_with_its_parcel_and_no_bounds(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), [], self::places());

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'Valdštejnovo náměstí', null, null));

        $address = $output->places[1];
        self::assertSame('address', $address->kind);
        self::assertSame(25344951, $address->code);
        self::assertSame('Valdštejnovo náměstí 58', $address->name);
        self::assertSame('Staré Město, Jičín', $address->context);
        self::assertSame(15.351066, $address->centerLongitude);
        self::assertSame(50.4373768, $address->centerLatitude);
        self::assertNull($address->west);
        self::assertNull($address->south);
        self::assertNull($address->east);
        self::assertNull($address->north);
        self::assertSame(1753295604, $address->parcelId);
    }

    #[Test]
    public function it_maps_a_municipality_with_its_bounds_and_the_kind_names(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), [], self::places());

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'Valdštejnovo náměstí', null, null));

        $municipality = $output->places[0];
        self::assertSame('municipality', $municipality->kind);
        self::assertSame(572659, $municipality->code);
        self::assertSame('Jičín', $municipality->name);
        self::assertSame('', $municipality->context);
        self::assertSame([15.0, 50.0, 15.2, 50.2], [$municipality->west, $municipality->south, $municipality->east, $municipality->north]);
        self::assertNull($municipality->parcelId);
    }

    #[Test]
    public function it_names_the_kind_of_every_place(): void
    {
        $places = [];
        foreach (PlaceKind::cases() as $kind) {
            $places[] = new Place($kind, 1, new MunicipalityCode(572659), 'Husova', 'Jičín', new Coordinates(15.1, 50.1), null, null);
        }
        $places[4] = self::places()[1];
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), [], $places);

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'husova', null, null));

        self::assertSame(
            ['municipality', 'cadastralArea', 'municipalityPart', 'street', 'address'],
            array_map(static fn(PlaceResultOutput $place): string => $place->kind, $output->places),
        );
    }

    #[Test]
    public function it_returns_no_more_than_the_limit_of_places(): void
    {
        $places = [];
        foreach (range(1, 10) as $code) {
            $places[] = new Place(PlaceKind::Street, $code, new MunicipalityCode(572659), 'Husova ' . $code, 'Jičín', new Coordinates(15.1, 50.1), null, null);
        }
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), [], $places);

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, 'husova', null, null));

        self::assertCount(8, $output->places);
    }

    #[Test]
    public function it_is_not_available_for_a_place_search_when_the_index_is_not_published(): void
    {
        $useCase = new SearchMapUseCase($this->tilesets(), new SearchIndexRepositoryUsingMemory(null));

        $this->expectException(SearchNotAvailable::class);

        $useCase->execute(new SearchMapInput(self::VERSION, 'jicin', null, null));
    }

    #[Test]
    public function it_returns_no_more_than_the_limit_of_parcels(): void
    {
        $matches = [];
        foreach (range(1, 10) as $id) {
            $matches[] = self::parcelMatch($id, ParcelKind::Land, 5, null);
        }
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), $matches);

        $output = $this->useCase($index)->execute(new SearchMapInput(self::VERSION, '5', null, null));

        self::assertCount(8, $output->parcels);
    }

    #[Test]
    public function it_is_not_available_when_no_data_is_imported(): void
    {
        $useCase = new SearchMapUseCase(new ParcelTilesetRepositoryUsingMemory(null), new SearchIndexRepositoryUsingMemory(null));

        $this->expectException(SearchNotAvailable::class);

        $useCase->execute(new SearchMapInput(self::VERSION, '72', null, null));
    }

    #[Test]
    #[DataProvider('unknownVersions')]
    public function it_is_not_available_for_another_or_malformed_version(string $version): void
    {
        $this->expectException(SearchNotAvailable::class);

        $this->useCase()->execute(new SearchMapInput($version, '72', null, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownVersions(): array
    {
        return ['another version' => ['19990101000000'], 'malformed' => ['not_a_version'], 'empty' => ['']];
    }

    #[Test]
    public function it_is_not_available_when_the_index_is_not_published(): void
    {
        $useCase = new SearchMapUseCase($this->tilesets(), new SearchIndexRepositoryUsingMemory(null));

        $this->expectException(SearchNotAvailable::class);

        $useCase->execute(new SearchMapInput(self::VERSION, '72', null, null));
    }

    #[Test]
    #[DataProvider('invalidTexts')]
    public function it_rejects_an_invalid_query(string $text): void
    {
        $this->expectException(InvalidSearchRequest::class);

        $this->useCase()->execute(new SearchMapInput(self::VERSION, $text, null, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTexts(): array
    {
        return ['empty' => [''], 'blank' => ['   '], 'too long' => [str_repeat('a', 101)]];
    }

    #[Test]
    #[DataProvider('invalidNears')]
    public function it_rejects_invalid_near_coordinates(?float $longitude, ?float $latitude): void
    {
        $this->expectException(InvalidSearchRequest::class);

        $this->useCase()->execute(new SearchMapInput(self::VERSION, '72', $longitude, $latitude));
    }

    /**
     * @return array<string, array{?float, ?float}>
     */
    public static function invalidNears(): array
    {
        return [
            'only longitude' => [15.35, null],
            'only latitude' => [null, 50.43],
            'longitude out of range' => [200.0, 50.0],
            'latitude out of range' => [15.0, 95.0],
        ];
    }

    #[Test]
    public function it_lets_an_unreadable_index_through(): void
    {
        $index = new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), [], unreadable: true);

        $this->expectException(SearchIndexUnreadable::class);

        $this->useCase($index)->execute(new SearchMapInput(self::VERSION, '72', null, null));
    }

    #[Test]
    public function it_lets_an_unreadable_tileset_through(): void
    {
        $tilesets = new ParcelTilesetRepositoryUsingMemory(null, unreadable: true);
        $useCase = new SearchMapUseCase($tilesets, new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION)));

        $this->expectException(ParcelTilesetUnreadable::class);

        $useCase->execute(new SearchMapInput(self::VERSION, '72', null, null));
    }

    private function useCase(?SearchIndexRepositoryUsingMemory $index = null): SearchMapUseCase
    {
        return new SearchMapUseCase(
            $this->tilesets(),
            $index ?? new SearchIndexRepositoryUsingMemory(new TilesetVersion(self::VERSION), self::parcelMatches()),
        );
    }

    private function tilesets(): ParcelTilesetRepositoryUsingMemory
    {
        return new ParcelTilesetRepositoryUsingMemory(new ParcelTileset(
            new TilesetVersion(self::VERSION),
            ExportDate::fromString('2026-10-31'),
            new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.5, 50.5)),
            9,
            16,
        ));
    }

    /**
     * @return list<ParcelMatch>
     */
    private static function parcelMatches(): array
    {
        return [
            self::parcelMatch(1753295604, ParcelKind::Building, 72, null),
            self::parcelMatch(99225194010, ParcelKind::Land, 867, 121),
        ];
    }

    /**
     * @return list<Place>
     */
    private static function places(): array
    {
        $address = new Address(
            'Valdštejnovo náměstí',
            'Staré Město',
            'Jičín',
            new HouseNumber(HouseNumberKind::Descriptive, 58, null, null),
            new Coordinates(15.351066, 50.4373768),
        );

        return [
            new Place(
                PlaceKind::Municipality,
                572659,
                new MunicipalityCode(572659),
                'Jičín',
                '',
                new Coordinates(15.1, 50.1),
                new Bounds(new Coordinates(15.0, 50.0), new Coordinates(15.2, 50.2)),
                null,
            ),
            Place::fromAddress(25344951, new MunicipalityCode(572659), $address, new ParcelId(1753295604)),
        ];
    }

    private static function parcelMatch(int $id, ParcelKind $kind, int $base, ?int $subdivision): ParcelMatch
    {
        return new ParcelMatch(
            new ParcelId($id),
            new ParcelNumber($kind, $base, $subdivision),
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín')),
        );
    }
}
