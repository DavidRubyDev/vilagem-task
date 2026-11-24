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

    #[Test]
    public function it_reads_the_query_parameters_decoded(): void
    {
        $request = Request::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/search/v1?q=Vald%C5%A1tejnovo+n%C3%A1m%C4%9Bst%C3%AD%2058&near=15.35,50.43']);

        self::assertSame('/api/search/v1', $request->path);
        self::assertSame('Valdštejnovo náměstí 58', $request->queryParameter('q'));
        self::assertSame('15.35,50.43', $request->queryParameter('near'));
    }

    #[Test]
    public function it_has_no_query_parameters_without_a_query_string(): void
    {
        $request = Request::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

        self::assertSame([], $request->queryParameters);
        self::assertNull($request->queryParameter('q'));
    }

    #[Test]
    public function it_keeps_an_empty_value_and_drops_array_parameters(): void
    {
        $request = Request::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x?q=&list[]=1&map[a]=2&near=1']);

        self::assertSame('', $request->queryParameter('q'));
        self::assertNull($request->queryParameter('list'));
        self::assertNull($request->queryParameter('map'));
        self::assertSame(['q' => '', 'near' => '1'], $request->queryParameters);
    }

    #[Test]
    public function it_drops_parameters_with_numeric_names(): void
    {
        $request = Request::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x?5=a&q=b']);

        self::assertSame(['q' => 'b'], $request->queryParameters);
    }

    #[Test]
    public function it_keeps_the_query_parameters_in_a_copy_with_path_parameters(): void
    {
        $request = new Request('GET', '/x', [], [], ['q' => '72']);

        $copy = $request->withPathParameters(['version' => 'v1']);

        self::assertSame(['q' => '72'], $copy->queryParameters);
        self::assertSame(['version' => 'v1'], $copy->pathParameters);
    }
}
