<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Import;

use ParcelMap\Application\Import\InvalidConvertedParcels;
use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AreaParcelCountTest extends TestCase
{
    #[Test]
    public function it_holds_the_area_and_its_parcel_count(): void
    {
        $count = new AreaParcelCount(new CadastralAreaCode(659541), 22);

        self::assertSame(659541, $count->area->value);
        self::assertSame(22, $count->parcelCount);
    }

    #[Test]
    public function it_rejects_an_area_without_parcels(): void
    {
        $this->expectException(InvalidConvertedParcels::class);
        $this->expectExceptionMessage('659541');

        new AreaParcelCount(new CadastralAreaCode(659541), 0);
    }
}
