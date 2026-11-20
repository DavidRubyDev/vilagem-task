<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final readonly class ImportedAreaOutput
{
    public function __construct(
        public int $code,
        public string $name,
        public int $parcelCount,
    ) {}
}
