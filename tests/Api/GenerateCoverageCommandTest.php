<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use ParcelMap\Tests\Support\LocalHttpServer;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenerateCoverageCommandTest extends TestCase
{
    private const string USAGE = 'Usage: bin/console coverage:generate --district=CODE [--export-date=YYYY-MM-DD]';

    private TemporaryDirectory $directory;
    private LocalHttpServer $server;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
        $this->server = LocalHttpServer::start(dirname(__DIR__) . '/Integration/Support/ruian-router.php');
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        $this->directory->remove();
    }

    #[Test]
    public function it_lists_the_coverage_command(): void
    {
        $result = ConsoleRunner::run([]);

        self::assertStringContainsString('coverage:generate', $result->stdout);
    }

    #[Test]
    public function it_writes_the_coverage_file_of_a_district_from_the_state_structure_file(): void
    {
        $result = $this->generate(['--district=3604', '--export-date=2026-10-31']);

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertSame(
            "Wrote 6 cadastral areas of 2 municipalities in district 3604 from the RÚIAN export of 2026-10-31.\n",
            $result->stdout,
        );
        self::assertSame(
            "code,name,municipality_code,municipality_name\n"
            . "601462,Běchary,572675,Běchary\n"
            . "659541,Jičín,572659,Jičín\n"
            . "725838,Popovice u Jičína,572659,Jičín\n"
            . "740217,Moravčice,572659,Jičín\n"
            . "740225,Robousy,572659,Jičín\n"
            . "771775,Hubálov,572659,Jičín\n",
            file_get_contents($this->coverageFile()),
        );
        self::assertFileExists($this->directory->path . '/cache/ruian/20261031_ST_UZSZ.xml.zip');
    }

    #[Test]
    public function it_fails_for_a_district_without_cadastral_areas_and_writes_nothing(): void
    {
        $result = $this->generate(['--district=3605', '--export-date=2026-10-31']);

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString(
            'Coverage generation failed: District 3605 has no cadastral areas in the RÚIAN export of 2026-10-31.',
            $result->stderr,
        );
        self::assertFileDoesNotExist($this->coverageFile());
    }

    #[Test]
    public function it_fails_when_the_state_structure_file_is_not_published(): void
    {
        $result = $this->generate(['--district=3604', '--export-date=2026-09-30']);

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString(
            'Coverage generation failed: The RÚIAN state structure file of 2026-09-30 is not published.',
            $result->stderr,
        );
    }

    #[Test]
    public function it_prints_the_usage_when_the_district_is_missing(): void
    {
        $result = $this->generate([]);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString(self::USAGE, $result->stderr);
    }

    #[Test]
    public function it_rejects_a_district_that_is_not_four_digits(): void
    {
        $result = $this->generate(['--district=36']);

        self::assertSame(2, $result->exitCode);
        self::assertStringStartsWith('Invalid request:', $result->stderr);
    }

    #[Test]
    public function it_rejects_a_district_that_is_not_a_number(): void
    {
        $result = $this->generate(['--district=abc']);

        self::assertSame(2, $result->exitCode);
        self::assertStringStartsWith('Invalid request:', $result->stderr);
    }

    #[Test]
    public function it_rejects_an_export_date_that_is_not_the_last_day_of_a_month(): void
    {
        $result = $this->generate(['--district=3604', '--export-date=2026-10-30']);

        self::assertSame(2, $result->exitCode);
        self::assertStringStartsWith('Invalid request:', $result->stderr);
    }

    #[Test]
    public function it_rejects_an_unknown_option(): void
    {
        $result = $this->generate(['--bogus']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('Unknown option "--bogus".', $result->stderr);
        self::assertStringContainsString(self::USAGE, $result->stderr);
    }

    /**
     * @param list<string> $arguments
     */
    private function generate(array $arguments): ConsoleResult
    {
        return ConsoleRunner::run(['coverage:generate', ...$arguments], [
            'PARCEL_MAP_DATA_DIR' => $this->directory->path,
            'PARCEL_MAP_COVERAGE_FILE' => $this->coverageFile(),
            'RUIAN_EXPORT_BASE_URL' => $this->server->baseUrl(),
        ]);
    }

    private function coverageFile(): string
    {
        return $this->directory->path . '/coverage.csv';
    }
}
