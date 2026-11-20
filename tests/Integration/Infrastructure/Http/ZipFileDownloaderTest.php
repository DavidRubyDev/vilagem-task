<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Http;

use ParcelMap\Infrastructure\Http\RemoteFileNotFound;
use ParcelMap\Infrastructure\Http\RemoteFileUnavailable;
use ParcelMap\Infrastructure\Http\ZipFileDownloader;
use ParcelMap\Tests\Support\LocalHttpServer;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZipFileDownloaderTest extends TestCase
{
    private const string FILE = '20261031_OB_572659_UKSH.xml.zip';

    private TemporaryDirectory $directory;
    private LocalHttpServer $server;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
        $this->server = LocalHttpServer::start(dirname(__DIR__, 2) . '/Support/ruian-router.php');
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        $this->directory->remove();
    }

    #[Test]
    public function it_downloads_the_file_to_the_cache_path_and_creates_the_directory(): void
    {
        $cachePath = $this->directory->path . '/cache/' . self::FILE;

        $file = new ZipFileDownloader()->download($this->server->baseUrl() . self::FILE, $cachePath);

        self::assertSame($cachePath, $file->path);
        self::assertFalse($file->fromCache);
        self::assertFileEquals(dirname(__DIR__, 3) . '/Fixtures/ruian/' . self::FILE, $cachePath);
        self::assertSame([], glob($this->directory->path . '/cache/*.part'));
    }

    #[Test]
    public function it_returns_the_cached_file_without_a_request(): void
    {
        $cachePath = $this->directory->path . '/' . self::FILE;
        new ZipFileDownloader()->download($this->server->baseUrl() . self::FILE, $cachePath);
        $this->server->stop();

        $file = new ZipFileDownloader()->download($this->server->baseUrl() . self::FILE, $cachePath);

        self::assertTrue($file->fromCache);
        self::assertSame($cachePath, $file->path);
    }

    #[Test]
    public function it_reports_a_missing_file(): void
    {
        $url = $this->server->baseUrl() . '20260930_OB_572659_UKSH.xml.zip';

        try {
            new ZipFileDownloader()->download($url, $this->directory->path . '/missing.zip');
            self::fail('Expected RemoteFileNotFound.');
        } catch (RemoteFileNotFound $exception) {
            self::assertStringContainsString($url, $exception->getMessage());
            self::assertSame([], glob($this->directory->path . '/*'));
        }
    }

    #[Test]
    public function it_reports_a_server_error_and_leaves_no_partial_file(): void
    {
        try {
            new ZipFileDownloader()->download($this->server->baseUrl() . '20261031_OB_500000_UKSH.xml.zip', $this->directory->path . '/a.zip');
            self::fail('Expected RemoteFileUnavailable.');
        } catch (RemoteFileUnavailable $exception) {
            self::assertStringContainsString('500', $exception->getMessage());
            self::assertSame([], glob($this->directory->path . '/*'));
        }
    }

    #[Test]
    public function it_rejects_a_body_that_is_not_a_zip_file_and_leaves_no_partial_file(): void
    {
        try {
            new ZipFileDownloader()->download($this->server->baseUrl() . '20261031_OB_500001_UKSH.xml.zip', $this->directory->path . '/a.zip');
            self::fail('Expected RemoteFileUnavailable.');
        } catch (RemoteFileUnavailable $exception) {
            self::assertStringContainsString('did not return a zip file', $exception->getMessage());
            self::assertSame([], glob($this->directory->path . '/*'));
        }
    }

    #[Test]
    public function it_reports_an_unreachable_server(): void
    {
        $this->server->stop();

        $this->expectException(RemoteFileUnavailable::class);

        new ZipFileDownloader()->download($this->server->baseUrl() . self::FILE, $this->directory->path . '/a.zip');
    }
}
