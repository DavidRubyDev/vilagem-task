<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

final readonly class SearchMapOutput
{
    /**
     * @param list<never> $places always empty until places are indexed
     * @param list<ParcelResultOutput> $parcels in ranked order
     */
    public function __construct(
        public array $places,
        public array $parcels,
    ) {}
}
