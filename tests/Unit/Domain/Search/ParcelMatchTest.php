<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Search;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use ParcelMap\Domain\Search\ParcelMatch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelMatchTest extends TestCase
{
    #[Test]
    public function it_is_equal_to_a_match_of_the_same_parcel(): void
    {
        $area = new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new Municipality(new MunicipalityCode(572659), 'Jičín'));

        $match = new ParcelMatch(new ParcelId(1753295604), new ParcelNumber(ParcelKind::Building, 72, null), $area);

        self::assertTrue($match->equals(new ParcelMatch(new ParcelId(1753295604), new ParcelNumber(ParcelKind::Building, 72, null), $area)));
        self::assertFalse($match->equals(new ParcelMatch(new ParcelId(1753296604), new ParcelNumber(ParcelKind::Building, 72, null), $area)));
    }
}
