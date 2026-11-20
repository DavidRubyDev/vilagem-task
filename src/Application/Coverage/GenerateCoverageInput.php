<?php

declare(strict_types=1);

namespace ParcelMap\Application\Coverage;

final readonly class GenerateCoverageInput
{
    /**
     * @param string|null $exportDate Y-m-d, or null for the latest published monthly export
     */
    public function __construct(
        public int $district,
        public ?string $exportDate,
    ) {}
}
