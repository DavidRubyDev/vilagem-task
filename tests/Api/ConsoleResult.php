<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

final readonly class ConsoleResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}
}
