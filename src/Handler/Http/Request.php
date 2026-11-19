<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
    ) {}

    /**
     * @param array<mixed> $server
     */
    public static function fromServer(array $server): self
    {
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = $server['REQUEST_URI'] ?? '/';

        $path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);

        return new self(
            strtoupper(is_string($method) ? $method : 'GET'),
            is_string($path) ? $path : '/',
        );
    }
}
