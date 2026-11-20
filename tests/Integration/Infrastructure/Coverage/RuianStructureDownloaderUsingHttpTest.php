<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Coverage;

use ParcelMap\Application\Coverage\StructureDownloadFailed;
use ParcelMap\Application\Coverage\StructureNotPublished;
use ParcelMap\Domain\Cadastre\ExportDate;
use ParcelMap\Infrastructure\Coverage\RuianStructureDownloaderUsingHttp;
use ParcelMap\Infrastructure\Http\ZipFileDownloader;
use ParcelMap\Tests\Support\LocalHttpServer;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuianStructureDownloaderUsingHttpTest extends TestCase
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
    public function it_downloads_the_structure_file_into_the_cache_under_the_expected_name(): void
    {
        $structure = $this->downloader()->download(ExportDate::fromString('2026-10-31'));

        $expectedPath = $this->cache->path . '/cache/20261031_ST_UZSZ.xml.zip';
        self::assertSame($expectedPath, $structure->path);
        self::assertSame('2026-10-31', $structure->date->toString());
        self::assertFalse($structure->fromCache);
        self::assertFileEquals(dirname(__DIR__, 3) . '/Fixtures/ruian/20261031_ST_UZSZ.xml.zip', $expectedPath);
    }

    #[Test]
    public function it_returns_the_cached_file_without_a_request(): void
    {
        $this->downloader()->download(ExportDate::fromString('2026-10-31'));
        $this->server->stop();

        $structure = $this->downloader()->download(ExportDate::fromString('2026-10-31'));

        self::assertTrue($structure->fromCache);
    }

    #[Test]
    public function it_reports_a_structure_file_that_is_not_published(): void
    {
        $this->expectException(StructureNotPublished::class);
        $this->expectExceptionMessage('The RÚIAN state structure file of 2026-09-30 is not published.');

        $this->downloader()->download(ExportDate::fromString('2026-09-30'));
    }

    #[Test]
    public function it_reports_an_unreachable_server(): void
    {
        $this->server->stop();

        $this->expectException(StructureDownloadFailed::class);

        $this->downloader()->download(ExportDate::fromString('2026-10-31'));
    }

    private function downloader(): RuianStructureDownloaderUsingHttp
    {
        return new RuianStructureDownloaderUsingHttp(new ZipFileDownloader(), $this->server->baseUrl(), $this->cache->path . '/cache');
    }
}
