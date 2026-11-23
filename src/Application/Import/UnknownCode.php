<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** A code outside the published code list that parcels use; it is kept and the operator is warned. */
final readonly class UnknownCode
{
    public function __construct(
        public CodeList $list,
        public int $code,
        public int $parcelCount,
    ) {
        if ($parcelCount < 1) {
            throw InvalidUnknownCode::noParcels($code);
        }
    }
}
