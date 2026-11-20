<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Import;

use ParcelMap\Application\Import\ExportNotPublished;
use ParcelMap\Application\Import\SourceDownloadFailed;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
use ParcelMap\Infrastructure\Http\ZipFileDownloader;
use ParcelMap\Infrastructure\Import\RuianExportDownloaderUsingHttp;
use ParcelMap\Tests\Support\LocalHttpServer;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuianExportDownloaderUsingHttpTest extends TestCase
{
    private TemporaryDirectory $cache;
    private LocalHttpServer $server;

    protected function setUp(): void
    {
        $this->cache = TemporaryDirectory::create();
        $this->server = LocalHttpServer::start(dirname(__DIR__, 2) . '/Support/ruian-router.php');
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        $this->cache->remove();
    }

    #[Test]
    public function it_downloads_the_export_into_the_cache_under_the_expected_name(): void
    {
        $export = $this->downloader()->download(new MunicipalityCode(572659), ExportDate::fromString('2026-10-31'));

        $expectedPath = $this->cache->path . '/cache/20261031_OB_572659_UKSH.xml.zip';
        self::assertSame($expectedPath, $export->path);
        self::assertSame(572659, $export->municipality->value);
        self::assertSame('2026-10-31', $export->date->toString());
        self::assertFileEquals(dirname(__DIR__, 3) . '/Fixtures/ruian/20261031_OB_572659_UKSH.xml.zip', $expectedPath);
        self::assertSame([], glob($this->cache->path . '/cache/*.part'));
    }

    #[Test]
    public function it_returns_the_cached_file_without_a_request_and_says_so(): void
    {
        $first = $this->downloader()->download(new MunicipalityCode(572659), ExportDate::fromString('2026-10-31'));
        $this->server->stop();

        $export = $this->downloader()->download(new MunicipalityCode(572659), ExportDate::fromString('2026-10-31'));

        self::assertFalse($first->fromCache);
        self::assertTrue($export->fromCache);
        self::assertFileExists($export->path);
    }

    #[Test]
    public function it_reports_an_export_that_is_not_published(): void
    {
        $this->expectException(ExportNotPublished::class);

        $this->downloader()->download(new MunicipalityCode(572659), ExportDate::fromString('2026-09-30'));
    }

    #[Test]
    public function it_reports_a_server_error_and_leaves_no_partial_file(): void
    {
        try {
            $this->downloader()->download(new MunicipalityCode(500000), ExportDate::fromString('2026-10-31'));
            self::fail('Expected SourceDownloadFailed.');
        } catch (SourceDownloadFailed $exception) {
            self::assertStringContainsString('500', $exception->getMessage());
            self::assertSame([], glob($this->cache->path . '/cache/*'));
        }
    }

    #[Test]
    public function it_rejects_a_body_that_is_not_a_zip_file_and_leaves_no_partial_file(): void
    {
        try {
            $this->downloader()->download(new MunicipalityCode(500001), ExportDate::fromString('2026-10-31'));
            self::fail('Expected SourceDownloadFailed.');
        } catch (SourceDownloadFailed) {
            self::assertSame([], glob($this->cache->path . '/cache/*'));
        }
    }

    #[Test]
    public function it_reports_an_unreachable_server(): void
    {
        $this->server->stop();

        $this->expectException(SourceDownloadFailed::class);

        $this->downloader()->download(new MunicipalityCode(572659), ExportDate::fromString('2026-10-31'));
    }

    private function downloader(): RuianExportDownloaderUsingHttp
    {
        return new RuianExportDownloaderUsingHttp(new ZipFileDownloader(), $this->server->baseUrl(), $this->cache->path . '/cache');
    }
}
