<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $headers lower-case names
     * @param array<string, string> $pathParameters values of the route's {placeholders}
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public array $pathParameters = [],
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
            self::headersFrom($server),
        );
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function withPathParameters(array $parameters): self
    {
        return new self($this->method, $this->path, $this->headers, $parameters);
    }

    /**
     * @param array<mixed> $server
     * @return array<string, string>
     */
    private static function headersFrom(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_') && is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        return $headers;
    }
}
