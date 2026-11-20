<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Process;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}
}
