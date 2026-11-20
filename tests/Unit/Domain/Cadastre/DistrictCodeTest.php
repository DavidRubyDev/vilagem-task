<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\DistrictCode;
use ParcelMap\Domain\Cadastre\InvalidDistrictCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DistrictCodeTest extends TestCase
{
    #[Test]
    #[DataProvider('validCodes')]
    public function it_accepts_a_four_digit_code(int $code): void
    {
        self::assertSame($code, new DistrictCode($code)->value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validCodes(): array
    {
        return ['lowest' => [1000], 'Jičín' => [3604], 'highest' => [9999]];
    }

    #[Test]
    #[DataProvider('invalidCodes')]
    public function it_rejects_a_code_that_is_not_four_digits(int $code): void
    {
        $this->expectException(InvalidDistrictCode::class);
        $this->expectExceptionMessage((string) $code);

        new DistrictCode($code);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidCodes(): array
    {
        return ['three digits' => [999], 'five digits' => [10000], 'negative' => [-3604], 'zero' => [0]];
    }

    #[Test]
    public function it_is_equal_to_a_district_code_with_the_same_value(): void
    {
        self::assertTrue(new DistrictCode(3604)->equals(new DistrictCode(3604)));
        self::assertFalse(new DistrictCode(3604)->equals(new DistrictCode(3608)));
    }
}
