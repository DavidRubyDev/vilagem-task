<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Handler\Http;

use ParcelMap\Handler\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    #[Test]
    public function it_takes_method_and_path_dropping_the_query_string(): void
    {
        $request = Request::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/map-config?x=1']);

        self::assertSame('GET', $request->method);
        self::assertSame('/api/map-config', $request->path);
    }

    #[Test]
    public function it_upper_cases_the_method(): void
    {
        $request = Request::fromServer(['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/']);

        self::assertSame('POST', $request->method);
    }

    #[Test]
    public function it_defaults_to_get_and_the_root_path_when_keys_are_missing(): void
    {
        $request = Request::fromServer([]);

        self::assertSame('GET', $request->method);
        self::assertSame('/', $request->path);
    }

    #[Test]
    public function it_reads_request_headers_with_lower_case_names(): void
    {
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
            'HTTP_USER_AGENT' => 'test',
            'CONTENT_TYPE' => 'ignored',
        ]);

        self::assertSame(['accept-encoding' => 'gzip, deflate', 'user-agent' => 'test'], $request->headers);
    }

    #[Test]
    public function it_looks_up_a_header_case_insensitively_with_a_default(): void
    {
        $request = new Request('GET', '/', ['accept-encoding' => 'gzip']);

        self::assertSame('gzip', $request->header('Accept-Encoding'));
        self::assertSame('', $request->header('Accept-Language'));
        self::assertSame('en', $request->header('Accept-Language', 'en'));
    }

    #[Test]
    public function it_returns_a_copy_with_path_parameters(): void
    {
        $request = new Request('GET', '/x');

        $copy = $request->withPathParameters(['version' => 'v1']);

        self::assertSame([], $request->pathParameters);
        self::assertSame(['version' => 'v1'], $copy->pathParameters);
        self::assertSame('/x', $copy->path);
    }
}
