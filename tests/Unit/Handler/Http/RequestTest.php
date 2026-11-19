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
}
