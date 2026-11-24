<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

interface PlaceConverter
{
    /**
     * Extracts the places of one municipality (parts, streets, buildings, address points) from its export.
     *
     * @throws ConversionFailed
     */
    public function convert(DownloadedExport $export): ConvertedPlaces;
}
