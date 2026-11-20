<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

interface MapSettingsRepository
{
    public function initialView(): MapView;

    public function baseMap(): BaseMap;
}
