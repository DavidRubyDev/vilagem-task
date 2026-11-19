<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

final readonly class HttpResult
{
    /**
     * @param array<string, string> $headers keys are lower-case
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
