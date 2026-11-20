<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\InvalidCadastralArea;
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

        new CadastralArea(new CadastralAreaCode(659541), $name, new MunicipalityCode(572659));
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
        $area = new CadastralArea(new CadastralAreaCode(659541), 'Jičín', new MunicipalityCode(572659));

        self::assertTrue($area->equals(new CadastralArea(new CadastralAreaCode(659541), 'Other', new MunicipalityCode(572660))));
        self::assertFalse($area->equals(new CadastralArea(new CadastralAreaCode(740225), 'Jičín', new MunicipalityCode(572659))));
    }
}
