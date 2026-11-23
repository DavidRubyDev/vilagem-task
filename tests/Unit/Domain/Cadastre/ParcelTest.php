<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\Area;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\LandType;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelBoundary;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Map\Coordinates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelTest extends TestCase
{
    #[Test]
    public function it_equals_by_id_only(): void
    {
        $parcel = self::parcel(1753295604, 72);

        self::assertTrue($parcel->equals(self::parcel(1753295604, 73)));
        self::assertFalse($parcel->equals(self::parcel(1761441604, 72)));
    }

    private static function parcel(int $id, int $baseNumber): Parcel
    {
        $position = new Coordinates(15.35, 50.43);

        return new Parcel(
            new ParcelId($id),
            new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín')),
            new ParcelNumber(ParcelKind::Building, $baseNumber, null),
            new LandType(13),
            null,
            new Area(455),
            ParcelBoundary::fromRings([[$position, new Coordinates(15.36, 50.43), new Coordinates(15.36, 50.44), $position]]),
        );
    }
}
