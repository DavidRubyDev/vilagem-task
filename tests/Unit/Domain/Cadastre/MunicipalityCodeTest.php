<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Domain\Cadastre\InvalidMunicipalityCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MunicipalityCodeTest extends TestCase
{
    #[Test]
    #[DataProvider('valid')]
    public function it_accepts_six_digit_codes(int $value): void
    {
        self::assertSame($value, new MunicipalityCode($value)->value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function valid(): array
    {
        return ['lowest' => [100000], 'highest' => [999999]];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_codes_that_are_not_six_digits(int $value): void
    {
        $this->expectException(InvalidMunicipalityCode::class);

        new MunicipalityCode($value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalid(): array
    {
        return ['five digits' => [99999], 'seven digits' => [1000000], 'negative' => [-572659]];
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        self::assertTrue(new MunicipalityCode(572659)->equals(new MunicipalityCode(572659)));
        self::assertFalse(new MunicipalityCode(572659)->equals(new MunicipalityCode(572667)));
    }
}
