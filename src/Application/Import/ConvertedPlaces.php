<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use ParcelMap\Domain\Cadastre\MunicipalityCode;

/** The places (parts, streets, buildings, address points) of one municipality converted for the search index builder; the path is opaque to the use case. */
final readonly class ConvertedPlaces
{
    public function __construct(
        public MunicipalityCode $municipality,
        public string $path,
    ) {}
}
