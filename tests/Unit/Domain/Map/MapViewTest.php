<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Domain\Map;

use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\MapView;
use ParcelMap\Domain\Map\ZoomLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MapViewTest extends TestCase
{
    #[Test]
    public function it_exposes_center_and_zoom(): void
    {
        $center = new Coordinates(15.35, 50.43);
        $zoom = new ZoomLevel(12.0);

        $view = new MapView($center, $zoom);

        self::assertSame($center, $view->center);
        self::assertSame($zoom, $view->zoom);
    }

    #[Test]
    public function it_equals_by_value(): void
    {
        $view = new MapView(new Coordinates(15.35, 50.43), new ZoomLevel(12.0));

        self::assertTrue($view->equals(new MapView(new Coordinates(15.35, 50.43), new ZoomLevel(12.0))));
        self::assertFalse($view->equals(new MapView(new Coordinates(15.35, 50.43), new ZoomLevel(13.0))));
        self::assertFalse($view->equals(new MapView(new Coordinates(15.36, 50.43), new ZoomLevel(12.0))));
    }
}
