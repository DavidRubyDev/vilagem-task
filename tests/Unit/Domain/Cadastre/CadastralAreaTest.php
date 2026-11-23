<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\InvalidCadastralArea;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CadastralAreaTest extends TestCase
{
    #[Test]
    #[DataProvider('blankNames')]
    public function it_rejects_an_empty_or_whitespace_name(string $name): void
    {
        $this->expectException(InvalidCadastralArea::class);

        new CadastralArea(new CadastralAreaCode(659541), $name, self::municipality(572659));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankNames(): array
    {
        return ['empty' => [''], 'spaces' => ['   ']];
    }

    #[Test]
    public function it_equals_by_code_only(): void
    {
        $area = new CadastralArea(new CadastralAreaCode(659541), 'Jičín', self::municipality(572659));

        self::assertTrue($area->equals(new CadastralArea(new CadastralAreaCode(659541), 'Other', self::municipality(572660))));
        self::assertFalse($area->equals(new CadastralArea(new CadastralAreaCode(740225), 'Jičín', self::municipality(572659))));
    }

    #[Test]
    public function it_holds_its_municipality(): void
    {
        $municipality = self::municipality(572659);

        self::assertSame($municipality, new CadastralArea(new CadastralAreaCode(659541), 'Jičín', $municipality)->municipality);
    }

    private static function municipality(int $code): Municipality
    {
        return new Municipality(new MunicipalityCode($code), 'Municipality ' . $code);
    }
}
