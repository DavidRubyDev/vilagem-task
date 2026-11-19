<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\InvalidTileUrlTemplate;
use ParcelMap\Domain\Map\TileUrlTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TileUrlTemplateTest extends TestCase
{
    #[Test]
    public function it_accepts_the_osm_template(): void
    {
        $template = new TileUrlTemplate('https://tile.openstreetmap.org/{z}/{x}/{y}.png');

        self::assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $template->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return [
            'http' => ['http://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            'missing z' => ['https://tile.example.org/{x}/{y}.png'],
            'missing x' => ['https://tile.example.org/{z}/{y}.png'],
            'missing y' => ['https://tile.example.org/{z}/{x}.png'],
            'empty' => [''],
            'not a url' => ['https://not a url/{z}/{x}/{y}'],
        ];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_an_invalid_template(string $value): void
    {
        $this->expectException(InvalidTileUrlTemplate::class);

        new TileUrlTemplate($value);
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        $template = new TileUrlTemplate('https://a.example.org/{z}/{x}/{y}.png');

        self::assertTrue($template->equals(new TileUrlTemplate('https://a.example.org/{z}/{x}/{y}.png')));
        self::assertFalse($template->equals(new TileUrlTemplate('https://b.example.org/{z}/{x}/{y}.png')));
    }
}
