<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidMunicipality;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MunicipalityTest extends TestCase
{
    #[Test]
    #[DataProvider('blankNames')]
    public function it_rejects_an_empty_or_whitespace_name(string $name): void
    {
        $this->expectException(InvalidMunicipality::class);
        $this->expectExceptionMessage('572659');

        new Municipality(new MunicipalityCode(572659), $name);
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
        $municipality = new Municipality(new MunicipalityCode(572659), 'Jičín');

        self::assertTrue($municipality->equals(new Municipality(new MunicipalityCode(572659), 'Other')));
        self::assertFalse($municipality->equals(new Municipality(new MunicipalityCode(572675), 'Jičín')));
    }
}
