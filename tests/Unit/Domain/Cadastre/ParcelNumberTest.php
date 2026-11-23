<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use ParcelMap\Domain\Cadastre\InvalidParcelNumber;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelNumberTest extends TestCase
{
    #[Test]
    #[DataProvider('zeroOrNegativeBaseNumbers')]
    public function it_rejects_a_parcel_number_with_a_zero_base_number(int $baseNumber): void
    {
        $this->expectException(InvalidParcelNumber::class);

        new ParcelNumber(ParcelKind::Land, $baseNumber, null);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function zeroOrNegativeBaseNumbers(): array
    {
        return ['zero' => [0], 'negative' => [-3]];
    }

    #[Test]
    #[DataProvider('zeroOrNegativeSubdivisions')]
    public function it_rejects_a_subdivision_below_one(int $subdivision): void
    {
        $this->expectException(InvalidParcelNumber::class);

        new ParcelNumber(ParcelKind::Land, 867, $subdivision);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function zeroOrNegativeSubdivisions(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    #[Test]
    #[DataProvider('numbers')]
    public function it_prints_the_official_notation(ParcelKind $kind, int $base, ?int $subdivision, string $expected): void
    {
        self::assertSame($expected, new ParcelNumber($kind, $base, $subdivision)->toString());
    }

    /**
     * @return array<string, array{ParcelKind, int, ?int, string}>
     */
    public static function numbers(): array
    {
        return [
            'building' => [ParcelKind::Building, 72, null, 'st. 72'],
            'building with subdivision' => [ParcelKind::Building, 80, 2, 'st. 80/2'],
            'land' => [ParcelKind::Land, 1171, null, '1171'],
            'land with subdivision' => [ParcelKind::Land, 867, 121, '867/121'],
        ];
    }

    #[Test]
    public function it_equals_by_kind_base_number_and_subdivision(): void
    {
        $number = new ParcelNumber(ParcelKind::Building, 72, null);

        self::assertTrue($number->equals(new ParcelNumber(ParcelKind::Building, 72, null)));
        self::assertFalse($number->equals(new ParcelNumber(ParcelKind::Land, 72, null)));
        self::assertFalse($number->equals(new ParcelNumber(ParcelKind::Building, 73, null)));
        self::assertFalse($number->equals(new ParcelNumber(ParcelKind::Building, 72, 1)));
    }
}
