<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $headers lower-case names
     * @param array<string, string> $pathParameters values of the route's {placeholders}
     * @param array<string, string> $queryParameters decoded; array parameters (`q[]=x`) are not kept
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public array $pathParameters = [],
        public array $queryParameters = [],
    ) {}

    /**
     * @param array<mixed> $server
     */
    public static function fromServer(array $server): self
    {
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = $server['REQUEST_URI'] ?? '/';

        $uri = is_string($uri) ? $uri : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            strtoupper(is_string($method) ? $method : 'GET'),
            is_string($path) ? $path : '/',
            self::headersFrom($server),
            [],
            self::queryParametersFrom($uri),
        );
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function queryParameter(string $name): ?string
    {
        return $this->queryParameters[$name] ?? null;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function withPathParameters(array $parameters): self
    {
        return new self($this->method, $this->path, $this->headers, $parameters, $this->queryParameters);
    }

    /**
     * @return array<string, string>
     */
    private static function queryParametersFrom(string $uri): array
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        if (!is_string($query)) {
            return [];
        }

        parse_str($query, $parsed);
        $parameters = [];
        foreach ($parsed as $name => $value) {
            // parse_str makes integer keys of numeric names and arrays of `name[]`; neither is a parameter we read.
            if (is_string($name) && is_string($value)) {
                $parameters[$name] = $value;
            }
        }

        return $parameters;
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
