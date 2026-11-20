<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Cadastre;

use ParcelMap\Domain\Cadastre\CoverageUnavailable;
use ParcelMap\Infrastructure\Cadastre\CoverageRepositoryUsingCsv;
use ParcelMap\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CoverageRepositoryUsingCsvTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = TemporaryDirectory::create();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    #[Test]
    public function it_reads_the_committed_coverage_file(): void
    {
        $coverage = new CoverageRepositoryUsingCsv(dirname(__DIR__, 4) . '/config/coverage.csv')->get();

        self::assertSame(
            [659541, 740225, 725838, 740217, 771775],
            array_map(static fn($area): int => $area->code->value, $coverage->areas()),
        );
        self::assertSame('Popovice u Jičína', $coverage->areas()[2]->name);
        self::assertCount(1, $coverage->municipalities());
        self::assertSame(572659, $coverage->municipalities()[0]->value);
    }

    #[Test]
    public function it_rejects_a_missing_file(): void
    {
        $this->expectException(CoverageUnavailable::class);

        new CoverageRepositoryUsingCsv($this->directory->path . '/missing.csv')->get();
    }

    #[Test]
    #[DataProvider('brokenFiles')]
    public function it_rejects_a_broken_file_and_names_the_file_and_line(string $content, string $expectedLine): void
    {
        $file = $this->directory->path . '/coverage.csv';
        file_put_contents($file, $content);

        try {
            new CoverageRepositoryUsingCsv($file)->get();
            self::fail('Expected CoverageUnavailable.');
        } catch (CoverageUnavailable $exception) {
            self::assertStringContainsString($file, $exception->getMessage());
            self::assertStringContainsString($expectedLine, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function brokenFiles(): array
    {
        $header = "code,name,municipality_code\n";

        return [
            'wrong header' => ["id,name,municipality\n659541,Jičín,572659\n", 'line 1'],
            'two fields' => [$header . "659541,Jičín\n", 'line 2'],
            'non-numeric code' => [$header . "abc,Jičín,572659\n", 'line 2'],
            'code out of range' => [$header . "123,Jičín,572659\n", 'line 2'],
            'empty name' => [$header . "659541,,572659\n", 'line 2'],
            'duplicate code' => [$header . "659541,Jičín,572659\n659541,Jičín,572659\n", 'coverage'],
            'no rows' => [$header, 'coverage'],
        ];
    }
}
