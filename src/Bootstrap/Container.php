<?php

declare(strict_types=1);

namespace ParcelMap\Bootstrap;

use ParcelMap\Application\Import\ImportParcelsUseCase;
use ParcelMap\Application\Map\GetMapConfigurationUseCase;
use ParcelMap\Application\Map\GetParcelTileUseCase;
use ParcelMap\Command\Console\ConsoleApplication;
use ParcelMap\Command\ImportParcelsCommand;
use ParcelMap\Handler\GetMapConfigurationHandler;
use ParcelMap\Handler\GetParcelTileHandler;
use ParcelMap\Handler\Http\HttpKernel;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\Http\StderrErrorReporter;
use ParcelMap\Handler\ParcelTileRoute;
use ParcelMap\Handler\ShowMapPageHandler;
use ParcelMap\Infrastructure\Cadastre\CoverageRepositoryUsingCsv;
use ParcelMap\Infrastructure\Clock\ClockUsingSystemTime;
use ParcelMap\Infrastructure\Import\ParcelConverterUsingOgr2ogr;
use ParcelMap\Infrastructure\Import\RuianExportDownloaderUsingHttp;
use ParcelMap\Infrastructure\Import\TilesetBuilderUsingTippecanoe;
use ParcelMap\Infrastructure\Import\TilesetPublisherUsingMbtiles;
use ParcelMap\Infrastructure\Map\MapSettingsRepositoryUsingConstants;
use ParcelMap\Infrastructure\Map\ParcelTilesetRepositoryUsingMbtiles;
use ParcelMap\Infrastructure\Process\ProcessRunner;

final readonly class Container
{
    private const string DEFAULT_DATA_DIR = 'data';
    private const string DEFAULT_COVERAGE_FILE = 'config/coverage.csv';
    private const string DEFAULT_RUIAN_BASE_URL = 'https://vdp.cuzk.gov.cz/vymenny_format/soucasna/';

    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private string $projectDir,
        private array $environment = [],
    ) {}

    public function console(): ConsoleApplication
    {
        $runner = new ProcessRunner();
        $dataDir = $this->path('PARCEL_MAP_DATA_DIR', self::DEFAULT_DATA_DIR);

        return new ConsoleApplication(
            new ImportParcelsCommand(
                new ImportParcelsUseCase(
                    new CoverageRepositoryUsingCsv($this->path('PARCEL_MAP_COVERAGE_FILE', self::DEFAULT_COVERAGE_FILE)),
                    new RuianExportDownloaderUsingHttp(
                        $this->environment['RUIAN_EXPORT_BASE_URL'] ?? self::DEFAULT_RUIAN_BASE_URL,
                        $dataDir . '/cache/ruian',
                    ),
                    new ParcelConverterUsingOgr2ogr($runner, $dataDir . '/build'),
                    new TilesetBuilderUsingTippecanoe($runner, $dataDir . '/build'),
                    new TilesetPublisherUsingMbtiles($this->tilesetFile()),
                    new ClockUsingSystemTime(),
                ),
            ),
        );
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
                new Route(
                    'GET',
                    ParcelTileRoute::PATTERN,
                    new GetParcelTileHandler(new GetParcelTileUseCase(new ParcelTilesetRepositoryUsingMbtiles($this->tilesetFile()))),
                ),
                new Route('GET', '/', new ShowMapPageHandler($this->projectDir . '/frontend/index.html')),
            ),
            new StderrErrorReporter(),
        );
    }

    private function tilesetFile(): string
    {
        return $this->path('PARCEL_MAP_DATA_DIR', self::DEFAULT_DATA_DIR) . '/tiles/parcels.mbtiles';
    }

    private function path(string $variable, string $default): string
    {
        $path = $this->environment[$variable] ?? $default;

        return str_starts_with($path, '/') ? $path : $this->projectDir . '/' . $path;
    }
}
