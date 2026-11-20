<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

use ParcelMap\Domain\Map\MapSettingsRepository;

final readonly class GetMapConfigurationUseCase
{
    public function __construct(private MapSettingsRepository $settings) {}

    public function execute(): MapConfigurationOutput
    {
        $view = $this->settings->initialView();
        $baseMap = $this->settings->baseMap();

        return new MapConfigurationOutput(
            new MapViewOutput($view->center->longitude, $view->center->latitude, $view->zoom->value),
            new BaseMapOutput(
                $baseMap->tileUrlTemplate->value,
                $baseMap->tileSize,
                $baseMap->maxZoom,
                $baseMap->attribution->text,
                $baseMap->attribution->url,
            ),
        );
    }
}
