<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

final readonly class SearchMapOutput
{
    /**
     * @param list<PlaceResultOutput> $places in ranked order
     * @param list<ParcelResultOutput> $parcels in ranked order
     */
    public function __construct(
        public array $places,
        public array $parcels,
    ) {}
}
