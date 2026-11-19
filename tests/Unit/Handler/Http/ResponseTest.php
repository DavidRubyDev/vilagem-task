<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function it_writes_json_with_slashes_and_non_ascii_characters_unescaped(): void
    {
        $response = Response::json(['text' => '© https://example.org/x']);

        self::assertSame(200, $response->status);
        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertSame('{"text":"© https://example.org/x"}', $response->body);
    }

    #[Test]
    public function it_sets_the_html_content_type(): void
    {
        $response = Response::html('<p>x</p>');

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame('<p>x</p>', $response->body);
    }

    #[Test]
    public function it_lists_the_allowed_methods_in_a_405_response(): void
    {
        $response = Response::methodNotAllowed(['GET']);

        self::assertSame(405, $response->status);
        self::assertSame('GET', $response->headers['Allow']);
        self::assertSame('{"error":"method_not_allowed"}', $response->body);
    }

    #[Test]
    public function it_builds_the_not_found_and_internal_error_responses(): void
    {
        self::assertSame(404, Response::notFound()->status);
        self::assertSame('{"error":"not_found"}', Response::notFound()->body);
        self::assertSame(500, Response::internalError()->status);
        self::assertSame('{"error":"internal_error"}', Response::internalError()->body);
    }
}
