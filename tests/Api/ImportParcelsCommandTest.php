<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use ParcelMap\Tests\Support\LocalHttpServer;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportParcelsCommandTest extends TestCase
{
    private TemporaryDirectory $dataDir;
    private ?LocalHttpServer $server = null;

    protected function setUp(): void
    {
        $this->dataDir = TemporaryDirectory::create();
        $this->server = LocalHttpServer::start(dirname(__DIR__) . '/Integration/Support/ruian-router.php');
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->dataDir->remove();
    }

    #[Test]
    public function it_lists_the_import_command(): void
    {
        $result = ConsoleRunner::run([]);

        self::assertStringContainsString('parcels:import', $result->stdout);
    }

    #[Test]
    public function it_imports_the_fixture_export_and_publishes_the_tileset(): void
    {
        $result = $this->import(['parcels:import', '--export-date=2026-10-31']);

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertStringContainsString('Imported the RÚIAN export of 2026-10-31.', $result->stdout);
        self::assertStringContainsString('659541 Jičín: 22 parcels', $result->stdout);
        self::assertStringContainsString('740225 Robousy: 3 parcels', $result->stdout);
        self::assertMatchesRegularExpression('/^Published tileset \d{14} with 25 parcels\.$/m', $result->stdout);
        self::assertSame('2026-10-31', $this->metadata('parcel_map_export_date'));
        self::assertFileExists($this->dataDir->path . '/cache/ruian/20261031_OB_572659_UKSH.xml.zip');
    }

    #[Test]
    public function it_reruns_from_the_cache_without_the_download_server(): void
    {
        self::assertSame(0, $this->import(['parcels:import', '--export-date=2026-10-31'])->exitCode);
        $this->server?->stop();

        $result = $this->import(['parcels:import', '--export-date=2026-10-31']);

        self::assertSame(0, $result->exitCode, $result->stderr);
    }

    #[Test]
    public function it_rejects_an_export_date_that_is_not_the_last_day_of_a_month(): void
    {
        $result = $this->import(['parcels:import', '--export-date=2026-10-30']);

        self::assertSame(2, $result->exitCode);
        self::assertStringStartsWith('Invalid --export-date:', $result->stderr);
    }

    #[Test]
    public function it_fails_when_the_export_is_not_published(): void
    {
        $result = $this->import(['parcels:import', '--export-date=2026-09-30']);

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString(
            'Import failed: The RÚIAN export of 2026-09-30 for municipality 572659 is not published.',
            $result->stderr,
        );
    }

    #[Test]
    public function it_rejects_an_unknown_option(): void
    {
        $result = $this->import(['parcels:import', '--bogus']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('Unknown option "--bogus".', $result->stderr);
        self::assertStringContainsString('Usage: bin/console parcels:import [--export-date=YYYY-MM-DD]', $result->stderr);
    }

    /**
     * @param list<string> $arguments
     */
    private function import(array $arguments): ConsoleResult
    {
        return ConsoleRunner::run($arguments, [
            'PARCEL_MAP_DATA_DIR' => $this->dataDir->path,
            'PARCEL_MAP_COVERAGE_FILE' => 'tests/Fixtures/coverage/fixture.csv',
            'RUIAN_EXPORT_BASE_URL' => $this->server?->baseUrl() ?? 'http://127.0.0.1:9/',
        ]);
    }

    private function metadata(string $name): string
    {
        try {
            $pdo = new PDO('sqlite:' . $this->dataDir->path . '/tiles/parcels.mbtiles');
        } catch (PDOException) {
            self::fail('The tileset was not published.');
        }
        $statement = $pdo->prepare('SELECT value FROM metadata WHERE name = ?');
        $statement->execute([$name]);

        return (string) $statement->fetchColumn();
    }
}
