<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use ParcelMap\Application\Map\GetMapConfigurationUseCase;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

final readonly class GetMapConfigurationHandler implements RequestHandler
{
    public function __construct(private GetMapConfigurationUseCase $getMapConfiguration) {}

    public function handle(Request $request): Response
    {
        $configuration = $this->getMapConfiguration->execute();
        $view = $configuration->initialView;
        $baseMap = $configuration->baseMap;

        $parcels = $configuration->parcels;

        return Response::json([
            'initialView' => [
                'center' => ['longitude' => $view->centerLongitude, 'latitude' => $view->centerLatitude],
                'zoom' => $view->zoom,
            ],
            'baseMap' => [
                'tileUrlTemplate' => $baseMap->tileUrlTemplate,
                'tileSize' => $baseMap->tileSize,
                'maxZoom' => $baseMap->maxZoom,
                'attribution' => ['text' => $baseMap->attributionText, 'url' => $baseMap->attributionUrl],
            ],
            'parcels' => $parcels === null ? null : [
                'tileUrlTemplate' => ParcelTileRoute::urlTemplate($parcels->tilesetVersion),
                'parcelUrlTemplate' => ParcelDetailRoute::urlTemplate($parcels->tilesetVersion),
                'minZoom' => $parcels->minZoom,
                'maxZoom' => $parcels->maxZoom,
                'bounds' => [
                    'west' => $parcels->west,
                    'south' => $parcels->south,
                    'east' => $parcels->east,
                    'north' => $parcels->north,
                ],
                'attribution' => ['text' => $parcels->attributionText, 'url' => $parcels->attributionUrl],
            ],
        ])->withHeader('Cache-Control', 'no-cache');
    }
}
