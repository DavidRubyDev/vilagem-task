<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MapPageTest extends TestCase
{
    #[Test]
    public function it_serves_the_map_page_shell(): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/');

        self::assertSame(200, $result->status);
        self::assertSame('text/html; charset=utf-8', $result->header('Content-Type'));
        self::assertStringContainsString('<div id="map">', $result->body);
        self::assertStringContainsString('src="/build/app.js"', $result->body);
        self::assertStringContainsString('href="/build/app.css"', $result->body);
        self::assertStringContainsString('<title>Jičín Parcel Map</title>', $result->body);
    }
}
