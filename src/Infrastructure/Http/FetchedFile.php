<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Http;

final readonly class FetchedFile
{
    public function __construct(
        public string $path,
        public bool $fromCache,
    ) {}
}
