<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Cadastre;

use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageNotSaved;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;
use ParcelMap\Domain\Cadastre\Municipality;
use ParcelMap\Domain\Cadastre\MunicipalityCode;
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
    public function it_reads_the_committed_coverage_file_of_the_whole_district(): void
    {
        $coverage = new CoverageRepositoryUsingCsv(dirname(__DIR__, 4) . '/config/coverage.csv')->get();

        $codes = array_map(static fn(CadastralArea $area): int => $area->code->value, $coverage->areas());
        self::assertCount(240, $codes);
        self::assertCount(111, $coverage->municipalities());
        $sorted = $codes;
        sort($sorted);
        self::assertSame($sorted, $codes);
        self::assertSame(count($codes), count(array_unique($codes)));

        $names = [];
        foreach ($coverage->areas() as $area) {
            $names[$area->code->value] = $area->name . ' ' . $area->municipality->code->value . ' ' . $area->municipality->name;
        }
        self::assertSame('Jičín 572659 Jičín', $names[659541]);
        self::assertSame('Robousy 572659 Jičín', $names[740225]);
        self::assertSame('Popovice u Jičína 572659 Jičín', $names[725838]);
        self::assertSame('Moravčice 572659 Jičín', $names[740217]);
        self::assertSame('Hubálov 572659 Jičín', $names[771775]);
    }

    #[Test]
    public function it_writes_the_header_and_one_row_per_area(): void
    {
        $file = $this->directory->path . '/coverage.csv';

        new CoverageRepositoryUsingCsv($file)->save(new Coverage(self::area(659541, 'Jičín'), self::area(725838, 'Popovice u Jičína')));

        self::assertSame("code,name,municipality_code,municipality_name\n659541,Jičín,572659,Jičín\n725838,Popovice u Jičína,572659,Jičín\n", file_get_contents($file));
        self::assertSame([], glob($file . '.tmp'));
    }

    #[Test]
    public function it_reads_back_what_it_saved_in_the_same_order(): void
    {
        $repository = new CoverageRepositoryUsingCsv($this->directory->path . '/coverage.csv');
        $coverage = new Coverage(self::area(740225, 'Robousy'), self::area(601462, 'Běchary', 572675, 'Běchary'), self::area(659541, 'Jičín'));

        $repository->save($coverage);

        self::assertEquals($coverage, $repository->get());
    }

    #[Test]
    public function it_quotes_a_name_with_a_comma_or_a_quote_and_reads_it_back(): void
    {
        $file = $this->directory->path . '/coverage.csv';
        $repository = new CoverageRepositoryUsingCsv($file);
        $coverage = new Coverage(self::area(659541, 'A, "B"'));

        $repository->save($coverage);

        self::assertSame("code,name,municipality_code,municipality_name\n659541,\"A, \"\"B\"\"\",572659,Jičín\n", file_get_contents($file));
        self::assertEquals($coverage, $repository->get());
    }

    #[Test]
    public function it_quotes_a_municipality_name_with_a_comma_and_reads_it_back(): void
    {
        $file = $this->directory->path . '/coverage.csv';
        $repository = new CoverageRepositoryUsingCsv($file);
        $coverage = new Coverage(self::area(659541, 'Jičín', 572659, 'Town, Upper'));

        $repository->save($coverage);

        self::assertStringContainsString(",572659,\"Town, Upper\"\n", (string) file_get_contents($file));
        self::assertEquals($coverage, $repository->get());
    }

    #[Test]
    public function it_does_not_quote_a_name_with_a_space(): void
    {
        $file = $this->directory->path . '/coverage.csv';

        new CoverageRepositoryUsingCsv($file)->save(new Coverage(self::area(659541, 'Bělá u Pecky')));

        self::assertStringContainsString("\n659541,Bělá u Pecky,572659,Jičín\n", (string) file_get_contents($file));
    }

    #[Test]
    public function it_replaces_an_existing_file_when_saving(): void
    {
        $file = $this->directory->path . '/coverage.csv';
        file_put_contents($file, "old content\n");

        new CoverageRepositoryUsingCsv($file)->save(new Coverage(self::area(659541, 'Jičín')));

        self::assertSame("code,name,municipality_code,municipality_name\n659541,Jičín,572659,Jičín\n", file_get_contents($file));
    }

    #[Test]
    public function it_fails_to_save_into_a_directory_that_does_not_exist_and_leaves_no_temporary_file(): void
    {
        $file = $this->directory->path . '/missing/coverage.csv';

        try {
            new CoverageRepositoryUsingCsv($file)->save(new Coverage(self::area(659541, 'Jičín')));
            self::fail('Expected CoverageNotSaved.');
        } catch (CoverageNotSaved $exception) {
            self::assertStringContainsString($file, $exception->getMessage());
            self::assertFileDoesNotExist($file . '.tmp');
        }
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
        $header = "code,name,municipality_code,municipality_name\n";

        return [
            'wrong header' => ["id,name,municipality,municipality_name\n659541,Jičín,572659,Jičín\n", 'line 1'],
            'old three-column header' => ["code,name,municipality_code\n659541,Jičín,572659\n", 'line 1'],
            'three fields' => [$header . "659541,Jičín,572659\n", 'expected 4 fields, found 3'],
            'non-numeric code' => [$header . "abc,Jičín,572659,Jičín\n", 'line 2'],
            'code out of range' => [$header . "123,Jičín,572659,Jičín\n", 'line 2'],
            'empty name' => [$header . "659541,,572659,Jičín\n", 'line 2'],
            'empty municipality name' => [$header . "659541,Jičín,572659,\n", 'line 2'],
            'inconsistent municipality names' => [$header . "659541,Jičín,572659,Jičín\n740225,Robousy,572659,Robousy\n", 'coverage'],
            'duplicate code' => [$header . "659541,Jičín,572659,Jičín\n659541,Jičín,572659,Jičín\n", 'coverage'],
            'no rows' => [$header, 'coverage'],
        ];
    }

    private static function area(int $code, string $name, int $municipality = 572659, string $municipalityName = 'Jičín'): CadastralArea
    {
        return new CadastralArea(new CadastralAreaCode($code), $name, new Municipality(new MunicipalityCode($municipality), $municipalityName));
    }
}
