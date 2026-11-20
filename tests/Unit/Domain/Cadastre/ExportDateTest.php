<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Cadastre;

use DateTimeImmutable;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\InvalidExportDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExportDateTest extends TestCase
{
    #[Test]
    #[DataProvider('validDates')]
    public function it_accepts_the_last_day_of_a_month(string $date): void
    {
        self::assertSame($date, ExportDate::fromString($date)->toString());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validDates(): array
    {
        return ['october' => ['2026-10-31'], 'leap february' => ['2024-02-29']];
    }

    #[Test]
    #[DataProvider('invalidDates')]
    public function it_rejects_anything_else(string $date): void
    {
        $this->expectException(InvalidExportDate::class);

        ExportDate::fromString($date);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'not the last day' => ['2026-10-30'],
            'trailing garbage' => ['2025-02-28x'],
            'no such month' => ['2026-13-31'],
            'not a date' => ['yesterday'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('latestPublished')]
    public function it_finds_the_last_day_of_the_previous_month(string $now, string $expected): void
    {
        self::assertSame($expected, ExportDate::latestPublishedOn(new DateTimeImmutable($now))->toString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function latestPublished(): array
    {
        return [
            'mid november' => ['2026-11-20 10:00:00', '2026-10-31'],
            'january' => ['2026-01-05', '2025-12-31'],
            'first of march' => ['2026-03-01', '2026-02-28'],
            'last of a month' => ['2026-10-31 23:59:59', '2026-09-30'],
        ];
    }

    #[Test]
    public function it_knows_the_previous_export_date(): void
    {
        self::assertSame('2026-02-28', ExportDate::fromString('2026-03-31')->previous()->toString());
    }

    #[Test]
    public function it_knows_its_year(): void
    {
        self::assertSame(2026, ExportDate::fromString('2026-10-31')->year());
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        self::assertTrue(ExportDate::fromString('2026-10-31')->equals(ExportDate::fromString('2026-10-31')));
        self::assertFalse(ExportDate::fromString('2026-10-31')->equals(ExportDate::fromString('2026-09-30')));
    }
}
