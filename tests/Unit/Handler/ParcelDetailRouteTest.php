<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\Response;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\ParcelDetailRoute;
use ParcelMap\Tests\Unit\Handler\Http\RecordingHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelDetailRouteTest extends TestCase
{
    #[Test]
    public function it_builds_a_url_template_that_keeps_the_id_placeholder(): void
    {
        self::assertSame('/api/parcels/20261120093000/{id}', ParcelDetailRoute::urlTemplate('20261120093000'));
    }

    #[Test]
    public function it_matches_a_url_filled_in_from_the_template(): void
    {
        $handler = new RecordingHandler(Response::html('detail'));
        $router = new Router(new Route('GET', ParcelDetailRoute::PATTERN, $handler));

        $router->dispatch(new Request('GET', '/api/parcels/20261120093000/1753295604'));

        self::assertSame(['version' => '20261120093000', 'id' => '1753295604'], $handler->received?->pathParameters);
    }
}
