<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnknownRouteTest extends TestCase
{
    #[Test]
    public function it_answers_an_unknown_path_with_a_json_404(): void
    {
        $result = HttpClient::fromEnvironment()->request('GET', '/does-not-exist');

        self::assertSame(404, $result->status);
        self::assertSame('application/json', $result->header('Content-Type'));
        self::assertSame('{"error":"not_found"}', $result->body);
    }
}
