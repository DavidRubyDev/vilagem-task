<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

final readonly class SearchMapInput
{
    public function __construct(
        public string $version,
        public string $query,
        public ?float $nearLongitude,
        public ?float $nearLatitude,
    ) {}
}
