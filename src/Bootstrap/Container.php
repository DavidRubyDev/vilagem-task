<?php

declare(strict_types=1);

namespace ParcelMap\Bootstrap;

use ParcelMap\Application\Map\GetMapConfigurationUseCase;
use ParcelMap\Command\Console\ConsoleApplication;
use ParcelMap\Handler\GetMapConfigurationHandler;
use ParcelMap\Handler\Http\HttpKernel;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\Http\StderrErrorReporter;
use ParcelMap\Handler\ShowMapPageHandler;
use ParcelMap\Infrastructure\Map\MapSettingsRepositoryUsingConstants;

final readonly class Container
{
    public function __construct(private string $projectDir) {}

    public function console(): ConsoleApplication
    {
        return new ConsoleApplication();
    }

    public function httpKernel(): HttpKernel
    {
        return new HttpKernel(
            new Router(
                new Route(
                    'GET',
                    '/api/map-config',
                    new GetMapConfigurationHandler(new GetMapConfigurationUseCase(new MapSettingsRepositoryUsingConstants())),
                ),
                new Route('GET', '/', new ShowMapPageHandler($this->projectDir . '/frontend/index.html')),
            ),
            new StderrErrorReporter(),
        );
    }
}
