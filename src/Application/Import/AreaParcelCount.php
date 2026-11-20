<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;

final readonly class AreaParcelCount
{
    public function __construct(
        public CadastralAreaCode $area,
        public int $parcelCount,
    ) {
        if ($parcelCount < 1) {
            throw new InvalidArgumentException('A cadastral area must have at least one converted parcel.');
        }
    }
}
