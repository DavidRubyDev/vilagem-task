<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Parcel;

use ParcelMap\Application\Parcel\GetParcelDetailUseCase;
use ParcelMap\Application\Parcel\ParcelDetailInput;
use ParcelMap\Application\Parcel\ParcelDetailNotFound;
use ParcelMap\Domain\Cadastre\Area;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\LandType;
use ParcelMap\Domain\Cadastre\LandUse;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Cadastre\ParcelStoreUnreadable;
use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\ParcelTilesetUnreadable;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Tests\Fake\ParcelRepositoryUsingMemory;
use ParcelMap\Tests\Fake\ParcelTilesetRepositoryUsingMemory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetParcelDetailUseCaseTest extends TestCase
{
    private const string VERSION = '20261120093000';

    #[Test]
    public function it_maps_a_building_parcel_without_land_use(): void
    {
        $output = $this->useCase()->execute(new ParcelDetailInput(self::VERSION, 1753295604));

        self::assertSame(1753295604, $output->id);
        self::assertSame('st. 72', $output->parcelNumber);
        self::assertSame('building', $output->kind);
        self::assertSame(659541, $output->cadastralAreaCode);
        self::assertSame('Jičín', $output->cadastralAreaName);
        self::assertSame(572659, $output->municipalityCode);
        self::assertSame('Jičín', $output->municipalityName);
        self::assertSame(13, $output->landTypeCode);
        self::assertNull($output->landUseCode);
        self::assertSame(455, $output->areaSquareMetres);
        self::assertSame(
            [[[15.35, 50.43], [15.36, 50.43], [15.36, 50.44], [15.35, 50.43]]],
            $output->boundaryRings,
        );
    }

    #[Test]
    public function it_maps_a_land_parcel_with_land_use_and_subdivision(): void
    {
        $output = $this->useCase()->execute(new ParcelDetailInput(self::VERSION, 99225194010));

        self::assertSame('867/121', $output->parcelNumber);
        self::assertSame('land', $output->kind);
        self::assertSame(26, $output->landUseCode);
    }

    #[Test]
    public function it_rejects_when_no_data_is_imported(): void
    {
        $useCase = new GetParcelDetailUseCase(new ParcelTilesetRepositoryUsingMemory(null), new ParcelRepositoryUsingMemory(null));

        $this->expectException(ParcelDetailNotFound::class);

        $useCase->execute(new ParcelDetailInput(self::VERSION, 1753295604));
    }

    #[Test]
    public function it_rejects_a_malformed_version(): void
    {
        $this->expectException(ParcelDetailNotFound::class);

        $this->useCase()->execute(new ParcelDetailInput('not/a/version', 1753295604));
    }

    #[Test]
    public function it_rejects_a_version_that_is_not_the_current_one(): void
    {
        $this->expectException(ParcelDetailNotFound::class);

        $this->useCase()->execute(new ParcelDetailInput('19990101000000', 1753295604));
    }

    #[Test]
    public function it_rejects_an_id_that_cannot_be_a_parcel_id(): void
    {
        $this->expectException(ParcelDetailNotFound::class);

        $this->useCase()->execute(new ParcelDetailInput(self::VERSION, 0));
    }

    #[Test]
    public function it_rejects_an_unknown_parcel(): void
    {
        $this->expectException(ParcelDetailNotFound::class);
        $this->expectExceptionMessage('999');

        $this->useCase()->execute(new ParcelDetailInput(self::VERSION, 999));
    }

    #[Test]
    public function it_rejects_every_parcel_when_the_store_of_the_current_version_is_missing(): void
    {
        $useCase = new GetParcelDetailUseCase($this->tilesets(), new ParcelRepositoryUsingMemory(null));

        $this->expectException(ParcelDetailNotFound::class);
        $this->expectExceptionMessage('have not been imported');

        $useCase->execute(new ParcelDetailInput(self::VERSION, 1753295604));
    }

    #[Test]
    public function it_lets_an_unreadable_store_through(): void
    {
        $useCase = new GetParcelDetailUseCase($this->tilesets(), new class implements \ParcelMap\Domain\Cadastre\ParcelRepository {
            public function get(TilesetVersion $version, ParcelId $id): Parcel
            {
                throw ParcelStoreUnreadable::because('broken');
            }
        });

        $this->expectException(ParcelStoreUnreadable::class);

        $useCase->execute(new ParcelDetailInput(self::VERSION, 1753295604));
    }

    private function useCase(): GetParcelDetailUseCase
    {
        return new GetParcelDetailUseCase(
            $this->tilesets(),
            new ParcelRepositoryUsingMemory(
                new TilesetVersion(self::VERSION),
                self::parcel(1753295604, ParcelKind::Building, 72, null, 13, null, 455),
                self::parcel(99225194010, ParcelKind::Land, 867, 121, 14, 26, 725),
            ),
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

    private static function parcel(int $id, ParcelKind $kind, int $base, ?int $subdivision, int $landType, ?int $landUse, int $area): Parcel
    {
        $start = new Coordinates(15.35, 50.43);

        return new Parcel(
            new ParcelId($id),
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín')),
            new ParcelNumber($kind, $base, $subdivision),
            new LandType($landType),
            $landUse === null ? null : new LandUse($landUse),
            new Area($area),
            ParcelBoundary::fromRings([[$start, new Coordinates(15.36, 50.43), new Coordinates(15.36, 50.44), $start]]),
        );
    }
}
