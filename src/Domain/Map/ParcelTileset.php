<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use ParcelMap\Domain\Cadastre\ExportDate;

/** The published parcel vector tileset: which import it came from, where it has data and at which zooms. */
final readonly class ParcelTileset
{
    private const string CONDITIONS_URL = 'https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx';
    private const int MAX_ZOOM = 24;

    public function __construct(
        public TilesetVersion $version,
        public ExportDate $exportDate,
        public Bounds $bounds,
        public int $minZoom,
        public int $maxZoom,
    ) {
        if ($minZoom < 0 || $minZoom > $maxZoom || $maxZoom > self::MAX_ZOOM) {
            throw InvalidParcelTileset::zoomRange($minZoom, $maxZoom);
        }
    }

    public function attribution(): Attribution
    {
        return new Attribution('ČÚZK, ' . $this->exportDate->year(), self::CONDITIONS_URL);
    }

    public function coversZoom(int $zoom): bool
    {
        return $zoom >= $this->minZoom && $zoom <= $this->maxZoom;
    }
}
