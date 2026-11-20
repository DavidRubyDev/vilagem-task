<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\Response;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    #[Test]
    public function it_dispatches_a_get_request_to_the_handler_registered_for_that_path(): void
    {
        $expected = Response::html('hello');
        $router = new Router(new Route('GET', '/hello', new FixedResponseHandler($expected)));

        $response = $router->dispatch(new Request('GET', '/hello'));

        self::assertSame($expected, $response);
    }

    #[Test]
    public function it_returns_404_for_an_unknown_path(): void
    {
        $router = new Router(new Route('GET', '/hello', new FixedResponseHandler(Response::html('x'))));

        $response = $router->dispatch(new Request('GET', '/other'));

        self::assertSame(404, $response->status);
        self::assertSame('{"error":"not_found"}', $response->body);
    }

    #[Test]
    public function it_returns_404_for_a_path_that_differs_only_by_a_trailing_slash(): void
    {
        $router = new Router(new Route('GET', '/hello', new FixedResponseHandler(Response::html('x'))));

        self::assertSame(404, $router->dispatch(new Request('GET', '/hello/'))->status);
    }

    #[Test]
    public function it_returns_405_with_the_allowed_methods_for_a_post_to_a_get_only_path(): void
    {
        $router = new Router(new Route('GET', '/hello', new FixedResponseHandler(Response::html('x'))));

        $response = $router->dispatch(new Request('POST', '/hello'));

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->headers['Allow']);
        self::assertSame('{"error":"method_not_allowed"}', $response->body);
    }

    #[Test]
    public function it_returns_404_for_the_root_path_when_no_routes_are_registered(): void
    {
        $response = new Router()->dispatch(new Request('GET', '/'));

        self::assertSame(404, $response->status);
    }

    #[Test]
    public function it_matches_a_placeholder_route_and_passes_the_parameters(): void
    {
        $handler = new RecordingHandler(Response::html('tile'));
        $router = new Router(new Route('GET', '/tiles/{version}/{z}/{x}/{y}.pbf', $handler));

        $response = $router->dispatch(new Request('GET', '/tiles/v-1_a/12/2222/1381.pbf'));

        self::assertSame('tile', $response->body);
        self::assertSame(
            ['version' => 'v-1_a', 'z' => '12', 'x' => '2222', 'y' => '1381'],
            $handler->received?->pathParameters,
        );
    }

    #[Test]
    #[DataProvider('nonMatchingPaths')]
    public function it_does_not_match_a_placeholder_across_slashes_dots_or_a_missing_suffix(string $path): void
    {
        $router = new Router(new Route('GET', '/tiles/{version}/{y}.pbf', new FixedResponseHandler(Response::html('x'))));

        self::assertSame(404, $router->dispatch(new Request('GET', $path))->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonMatchingPaths(): array
    {
        return [
            'slash in placeholder' => ['/tiles/a/b/1.pbf'],
            'dot in placeholder' => ['/tiles/a.b/1.pbf'],
            'wrong suffix' => ['/tiles/v1/1.png'],
            'empty placeholder' => ['/tiles//1.pbf'],
            'extra characters before' => ['/xtiles/v1/1.pbf'],
        ];
    }

    #[Test]
    public function it_returns_405_for_a_placeholder_route_requested_with_another_method(): void
    {
        $router = new Router(new Route('GET', '/tiles/{version}/{y}.pbf', new FixedResponseHandler(Response::html('x'))));

        $response = $router->dispatch(new Request('POST', '/tiles/v1/1.pbf'));

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->headers['Allow']);
    }
}
