<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\Response;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\ParcelTileRoute;
use ParcelMap\Tests\Unit\Handler\Http\RecordingHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelTileRouteTest extends TestCase
{
    #[Test]
    public function it_builds_a_url_template_that_keeps_the_tile_placeholders(): void
    {
        self::assertSame('/tiles/parcels/20261120093000/{z}/{x}/{y}.pbf', ParcelTileRoute::urlTemplate('20261120093000'));
    }

    #[Test]
    public function it_matches_a_url_filled_in_from_the_template(): void
    {
        $handler = new RecordingHandler(Response::html('tile'));
        $router = new Router(new Route('GET', ParcelTileRoute::PATTERN, $handler));

        $router->dispatch(new Request('GET', '/tiles/parcels/20261120093000/12/2222/1381.pbf'));

        self::assertSame(
            ['version' => '20261120093000', 'z' => '12', 'x' => '2222', 'y' => '1381'],
            $handler->received?->pathParameters,
        );
    }
}
