<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\HttpKernel;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\Response;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HttpKernelTest extends TestCase
{
    #[Test]
    public function it_returns_the_router_response_unchanged_when_no_error_occurs(): void
    {
        $expected = Response::html('ok');
        $kernel = new HttpKernel(
            new Router(new Route('GET', '/', new FixedResponseHandler($expected))),
            new RecordingErrorReporter(),
        );

        self::assertSame($expected, $kernel->handle(new Request('GET', '/')));
    }

    #[Test]
    public function it_answers_500_and_reports_the_throwable_when_a_handler_throws(): void
    {
        $error = new RuntimeException('boom');
        $reporter = new RecordingErrorReporter();
        $kernel = new HttpKernel(new Router(new Route('GET', '/', new ThrowingHandler($error))), $reporter);

        $response = $kernel->handle(new Request('GET', '/'));

        self::assertSame(500, $response->status);
        self::assertSame('{"error":"internal_error"}', $response->body);
        self::assertSame([$error], $reporter->reported);
    }
}
