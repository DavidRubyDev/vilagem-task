<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Map\MapSettings;
use ParcelMap\Domain\Map\BaseMap;
use ParcelMap\Domain\Map\MapView;

final readonly class InMemoryMapSettings implements MapSettings
{
    public function __construct(
        private MapView $initialView,
        private BaseMap $baseMap,
    ) {}

    public function initialView(): MapView
    {
        return $this->initialView;
    }

    public function baseMap(): BaseMap
    {
        return $this->baseMap;
    }
}
