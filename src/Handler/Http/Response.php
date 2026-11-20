<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    /**
     * @param array<string, mixed> $data a JSON tree, so its values are necessarily mixed
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    public static function html(string $html): self
    {
        return new self(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function noContent(array $headers = []): self
    {
        return new self(204, $headers, '');
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [...$this->headers, $name => $value], $this->body);
    }

    public static function notFound(): self
    {
        return self::json(['error' => 'not_found'], 404);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        $response = self::json(['error' => 'method_not_allowed'], 405);

        return new self(
            $response->status,
            $response->headers + ['Allow' => implode(', ', $allowedMethods)],
            $response->body,
        );
    }

    public static function internalError(): self
    {
        return self::json(['error' => 'internal_error'], 500);
    }
}
