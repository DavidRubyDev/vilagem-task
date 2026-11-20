<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;

/** The parcels of one cadastral area converted for the tile builder; the path is opaque to the use case. */
final readonly class ConvertedParcels
{
    public function __construct(
        public CadastralAreaCode $area,
        public string $path,
        public int $parcelCount,
    ) {
        if ($parcelCount < 1) {
            throw new InvalidArgumentException('Converted parcels must contain at least one parcel.');
        }
    }
}
