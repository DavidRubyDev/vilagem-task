<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\Attribution;
use ParcelMap\Domain\Map\InvalidAttribution;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributionTest extends TestCase
{
    #[Test]
    public function it_accepts_the_osm_attribution(): void
    {
        $attribution = new Attribution('© OpenStreetMap contributors', 'https://www.openstreetmap.org/copyright');

        self::assertSame('© OpenStreetMap contributors', $attribution->text);
        self::assertSame('https://www.openstreetmap.org/copyright', $attribution->url);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalid(): array
    {
        return [
            'empty text' => ['', 'https://www.openstreetmap.org/copyright'],
            'blank text' => ["  \t", 'https://www.openstreetmap.org/copyright'],
            'http url' => ['OSM', 'http://www.openstreetmap.org/copyright'],
            'not a url' => ['OSM', 'not a url'],
        ];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_invalid_attributions(string $text, string $url): void
    {
        $this->expectException(InvalidAttribution::class);

        new Attribution($text, $url);
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        $attribution = new Attribution('OSM', 'https://www.openstreetmap.org/copyright');

        self::assertTrue($attribution->equals(new Attribution('OSM', 'https://www.openstreetmap.org/copyright')));
        self::assertFalse($attribution->equals(new Attribution('Other', 'https://www.openstreetmap.org/copyright')));
    }
}
