<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

use ParcelMap\Domain\Map\BaseMap;
use ParcelMap\Domain\Map\MapView;

interface MapSettings
{
    public function initialView(): MapView;

    public function baseMap(): BaseMap;
}
