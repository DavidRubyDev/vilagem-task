<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\CadastralAreaCode;

final readonly class AreaParcelCount
{
    /**
     * @throws InvalidConvertedParcels when the area has no parcels
     */
    public function __construct(
        public CadastralAreaCode $area,
        public int $parcelCount,
    ) {
        if ($parcelCount < 1) {
            throw InvalidConvertedParcels::noParcels($area->value);
        }
    }
}
