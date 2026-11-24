<?php

declare(strict_types=1);

namespace ParcelMap\Bootstrap;

use ParcelMap\Application\Coverage\GenerateCoverageUseCase;
use ParcelMap\Application\Import\ImportParcelsUseCase;
use ParcelMap\Application\Map\GetMapConfigurationUseCase;
use ParcelMap\Application\Map\GetParcelTileUseCase;
use ParcelMap\Application\Parcel\GetParcelDetailUseCase;
use ParcelMap\Application\Search\SearchMapUseCase;
use ParcelMap\Command\Console\ConsoleApplication;
use ParcelMap\Command\GenerateCoverageCommand;
use ParcelMap\Command\ImportParcelsCommand;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Handler\GetMapConfigurationHandler;
use ParcelMap\Handler\GetParcelDetailHandler;
use ParcelMap\Handler\GetParcelTileHandler;
use ParcelMap\Handler\Http\HttpKernel;
use ParcelMap\Handler\Http\Route;
use ParcelMap\Handler\Http\Router;
use ParcelMap\Handler\Http\StderrErrorReporter;
use ParcelMap\Handler\ParcelDetailRoute;
use ParcelMap\Handler\ParcelTileRoute;
use ParcelMap\Handler\SearchMapHandler;
use ParcelMap\Handler\SearchRoute;
use ParcelMap\Handler\ShowMapPageHandler;
use ParcelMap\Infrastructure\Cadastre\CoverageRepositoryUsingCsv;
use ParcelMap\Infrastructure\Cadastre\ParcelRepositoryUsingSqlite;
use ParcelMap\Infrastructure\Clock\ClockUsingSystemTime;
use ParcelMap\Infrastructure\Coverage\DistrictAreaExtractorUsingOgr2ogr;
use ParcelMap\Infrastructure\Coverage\RuianStructureDownloaderUsingHttp;
use ParcelMap\Infrastructure\Http\ZipFileDownloader;
use ParcelMap\Infrastructure\Import\ParcelConverterUsingOgr2ogr;
use ParcelMap\Infrastructure\Import\ParcelDataPublisherUsingSqlite;
use ParcelMap\Infrastructure\Import\ParcelStoreBuilderUsingSqlite;
use ParcelMap\Infrastructure\Import\PlaceConverterUsingOgr2ogr;
use ParcelMap\Infrastructure\Import\RuianExportDownloaderUsingHttp;
use ParcelMap\Infrastructure\Import\SearchIndexBuilderUsingSqlite;
use ParcelMap\Infrastructure\Import\TilesetBuilderUsingTippecanoe;
use ParcelMap\Infrastructure\Import\TilesetInspectorUsingMbtiles;
use ParcelMap\Infrastructure\Map\MapSettingsRepositoryUsingConstants;
use ParcelMap\Infrastructure\Map\ParcelTilesetRepositoryUsingMbtiles;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use ParcelMap\Infrastructure\Search\SearchIndexRepositoryUsingSqlite;

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
        $zipDownloader = new ZipFileDownloader();
        $ruianBaseUrl = $this->environment['RUIAN_EXPORT_BASE_URL'] ?? self::DEFAULT_RUIAN_BASE_URL;
        $coverage = new CoverageRepositoryUsingCsv($this->path('PARCEL_MAP_COVERAGE_FILE', self::DEFAULT_COVERAGE_FILE));

        return new ConsoleApplication(
            new ImportParcelsCommand(
                new ImportParcelsUseCase(
                    $coverage,
                    new RuianExportDownloaderUsingHttp($zipDownloader, $ruianBaseUrl, $dataDir . '/cache/ruian'),
                    new ParcelConverterUsingOgr2ogr($runner, $dataDir . '/build'),
                    new PlaceConverterUsingOgr2ogr($runner, $dataDir . '/build'),
                    new TilesetBuilderUsingTippecanoe($runner, $dataDir . '/build'),
                    new TilesetInspectorUsingMbtiles(),
                    new ParcelStoreBuilderUsingSqlite($dataDir . '/build'),
                    new SearchIndexBuilderUsingSqlite($dataDir . '/build'),
                    new ParcelDataPublisherUsingSqlite($this->tilesetFile(), $this->parcelStoreDirectory(), $this->searchIndexDirectory()),
                    new ClockUsingSystemTime(),
                    TileSizeBudget::smoothMap(),
                ),
            ),
            new GenerateCoverageCommand(
                new GenerateCoverageUseCase(
                    new RuianStructureDownloaderUsingHttp($zipDownloader, $ruianBaseUrl, $dataDir . '/cache/ruian'),
                    new DistrictAreaExtractorUsingOgr2ogr($runner),
                    $coverage,
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
                    new GetMapConfigurationHandler(new GetMapConfigurationUseCase(
                        new MapSettingsRepositoryUsingConstants(),
                        new ParcelTilesetRepositoryUsingMbtiles($this->tilesetFile()),
                        new ParcelRepositoryUsingSqlite($this->parcelStoreDirectory()),
                        new SearchIndexRepositoryUsingSqlite($this->searchIndexDirectory()),
                    )),
                ),
                new Route(
                    'GET',
                    ParcelTileRoute::PATTERN,
                    new GetParcelTileHandler(new GetParcelTileUseCase(new ParcelTilesetRepositoryUsingMbtiles($this->tilesetFile()))),
                ),
                new Route(
                    'GET',
                    ParcelDetailRoute::PATTERN,
                    new GetParcelDetailHandler(new GetParcelDetailUseCase(
                        new ParcelTilesetRepositoryUsingMbtiles($this->tilesetFile()),
                        new ParcelRepositoryUsingSqlite($this->parcelStoreDirectory()),
                    )),
                ),
                new Route(
                    'GET',
                    SearchRoute::PATTERN,
                    new SearchMapHandler(new SearchMapUseCase(
                        new ParcelTilesetRepositoryUsingMbtiles($this->tilesetFile()),
                        new SearchIndexRepositoryUsingSqlite($this->searchIndexDirectory()),
                    )),
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

    private function parcelStoreDirectory(): string
    {
        return $this->path('PARCEL_MAP_DATA_DIR', self::DEFAULT_DATA_DIR) . '/parcels';
    }

    private function searchIndexDirectory(): string
    {
        return $this->path('PARCEL_MAP_DATA_DIR', self::DEFAULT_DATA_DIR) . '/search';
    }

    private function path(string $variable, string $default): string
    {
        $path = $this->environment[$variable] ?? $default;

        return str_starts_with($path, '/') ? $path : $this->projectDir . '/' . $path;
    }
}
