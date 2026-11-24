<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

use ParcelMap\Domain\Cadastre\ParcelRepository;
use ParcelMap\Domain\Map\MapSettingsRepository;
use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTileset;
use ParcelMap\Domain\Map\ParcelTilesetRepository;
use ParcelMap\Domain\Search\SearchIndexRepository;

final readonly class GetMapConfigurationUseCase
{
    public function __construct(
        private MapSettingsRepository $settings,
        private ParcelTilesetRepository $tilesets,
        private ParcelRepository $parcels,
        private SearchIndexRepository $searchIndex,
    ) {}

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
            $this->parcelLayer(),
        );
    }

    private function parcelLayer(): ?ParcelLayerOutput
    {
        try {
            $tileset = $this->tilesets->current();
        } catch (ParcelDataNotImported) {
            return null;
        }

        return $this->layerOf($tileset);
    }

    private function layerOf(ParcelTileset $tileset): ParcelLayerOutput
    {
        $attribution = $tileset->attribution();
        $detailsAvailable = $this->parcels->isPublished($tileset->version);

        return new ParcelLayerOutput(
            $tileset->version->value,
            $tileset->minZoom,
            $tileset->maxZoom,
            $tileset->bounds->southWest->longitude,
            $tileset->bounds->southWest->latitude,
            $tileset->bounds->northEast->longitude,
            $tileset->bounds->northEast->latitude,
            $attribution->text,
            $attribution->url,
            $detailsAvailable,
            $detailsAvailable && $this->searchIndex->isPublished($tileset->version),
        );
    }
}
