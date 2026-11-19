<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Application\Map;

use ParcelMap\Application\Map\GetMapConfiguration;
use ParcelMap\Domain\Map\Attribution;
use ParcelMap\Domain\Map\BaseMap;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\MapView;
use ParcelMap\Domain\Map\TileUrlTemplate;
use ParcelMap\Domain\Map\ZoomLevel;
use ParcelMap\Tests\Fake\InMemoryMapSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMapConfigurationTest extends TestCase
{
    #[Test]
    public function it_maps_the_settings_to_the_output_field_by_field(): void
    {
        $settings = new InMemoryMapSettings(
            new MapView(new Coordinates(longitude: 15.5, latitude: 50.25), new ZoomLevel(11.5)),
            new BaseMap(
                new TileUrlTemplate('https://tiles.example.org/{z}/{x}/{y}.png'),
                512,
                17,
                new Attribution('Example credit', 'https://example.org/credit'),
            ),
        );

        $output = new GetMapConfiguration($settings)->execute();

        self::assertSame(15.5, $output->initialView->centerLongitude);
        self::assertSame(50.25, $output->initialView->centerLatitude);
        self::assertSame(11.5, $output->initialView->zoom);
        self::assertSame('https://tiles.example.org/{z}/{x}/{y}.png', $output->baseMap->tileUrlTemplate);
        self::assertSame(512, $output->baseMap->tileSize);
        self::assertSame(17, $output->baseMap->maxZoom);
        self::assertSame('Example credit', $output->baseMap->attributionText);
        self::assertSame('https://example.org/credit', $output->baseMap->attributionUrl);
    }
}
