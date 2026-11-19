<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

final readonly class Route
{
    public function __construct(
        public string $method,
        public string $path,
        public RequestHandler $handler,
    ) {}
}
