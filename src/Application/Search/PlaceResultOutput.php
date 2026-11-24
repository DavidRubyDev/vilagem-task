<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

final readonly class PlaceResultOutput
{
    /**
     * @param string $kind 'municipality', 'cadastralArea', 'municipalityPart', 'street' or 'address'
     * @param ?float $west all four bounds are null for a place that is a point, or all are set
     * @param ?int $parcelId the parcel an address's building stands on
     */
    public function __construct(
        public string $kind,
        public int $code,
        public string $name,
        public string $context,
        public float $centerLongitude,
        public float $centerLatitude,
        public ?float $west,
        public ?float $south,
        public ?float $east,
        public ?float $north,
        public ?int $parcelId,
    ) {}
}
