<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\Response;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\SearchRoute;
use ParcelMap\Tests\Unit\Handler\Http\RecordingHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchRouteTest extends TestCase
{
    #[Test]
    public function it_builds_the_url_of_one_version(): void
    {
        self::assertSame('/api/search/20261120093000', SearchRoute::url('20261120093000'));
    }

    #[Test]
    public function it_matches_a_url_built_from_it(): void
    {
        $handler = new RecordingHandler(Response::html('found'));
        $router = new Router(new Route('GET', SearchRoute::PATTERN, $handler));

        $router->dispatch(new Request('GET', SearchRoute::url('20261120093000')));

        self::assertSame(['version' => '20261120093000'], $handler->received?->pathParameters);
    }
}
