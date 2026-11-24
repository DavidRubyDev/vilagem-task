<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Import;

use ParcelMap\Application\Import\IndexedPlaceCounts;
use ParcelMap\Application\Import\InvalidIndexedPlaceCounts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndexedPlaceCountsTest extends TestCase
{
    #[Test]
    public function it_holds_the_count_of_every_kind_including_zero(): void
    {
        $counts = new IndexedPlaceCounts(1, 0, 3, 4, 5);

        self::assertSame([1, 0, 3, 4, 5], [$counts->municipalities, $counts->cadastralAreas, $counts->municipalityParts, $counts->streets, $counts->addresses]);
    }

    #[Test]
    #[DataProvider('negative')]
    public function it_rejects_a_negative_count(int $municipalities, int $areas, int $parts, int $streets, int $addresses, string $kind): void
    {
        try {
            new IndexedPlaceCounts($municipalities, $areas, $parts, $streets, $addresses);
            self::fail('Expected InvalidIndexedPlaceCounts.');
        } catch (InvalidIndexedPlaceCounts $exception) {
            self::assertStringContainsString($kind, $exception->getMessage());
            self::assertStringContainsString('-1', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{int, int, int, int, int, string}>
     */
    public static function negative(): array
    {
        return [
            'municipalities' => [-1, 0, 0, 0, 0, 'municipalities'],
            'cadastral areas' => [0, -1, 0, 0, 0, 'cadastral areas'],
            'parts' => [0, 0, -1, 0, 0, 'parts of municipalities'],
            'streets' => [0, 0, 0, -1, 0, 'streets'],
            'addresses' => [0, 0, 0, 0, -1, 'addresses'],
        ];
    }
}
