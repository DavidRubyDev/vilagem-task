<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidParcelKind;
use ParcelMap\Domain\Cadastre\ParcelKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelKindTest extends TestCase
{
    #[Test]
    public function it_maps_the_numbering_codes_to_building_and_land(): void
    {
        self::assertSame(ParcelKind::Building, ParcelKind::fromCode(1));
        self::assertSame(ParcelKind::Land, ParcelKind::fromCode(2));
    }

    #[Test]
    public function it_rejects_an_unknown_code(): void
    {
        $this->expectException(InvalidParcelKind::class);
        $this->expectExceptionMessage('3');

        ParcelKind::fromCode(3);
    }
}
