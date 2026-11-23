<?php

declare(strict_types=1);

namespace ParcelMap\Application\Parcel;

final readonly class ParcelDetailInput
{
    public function __construct(
        public string $version,
        public int $parcelId,
    ) {}
}
