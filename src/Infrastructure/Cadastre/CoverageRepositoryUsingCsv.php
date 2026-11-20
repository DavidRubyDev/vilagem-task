<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Cadastre;

use InvalidArgumentException;
use ParcelMap\Domain\Cadastre\CadastralArea;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageNotSaved;
use ParcelMap\Domain\Cadastre\CoverageRepository;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;
use ParcelMap\Domain\Cadastre\MunicipalityCode;

/**
 * Reads and writes the covered cadastral areas as a UTF-8 CSV file with the header `code,name,municipality_code`.
 * A field is quoted only when it has to be (a comma, a double quote or a line break), unlike fputcsv, which also
 * quotes spaces, so the committed file stays readable and its diffs small.
 */
final readonly class CoverageRepositoryUsingCsv implements CoverageRepository
{
    private const array HEADER = ['code', 'name', 'municipality_code'];

    public function __construct(private string $file) {}

    public function get(): Coverage
    {
        $handle = is_file($this->file) ? @fopen($this->file, 'r') : false;
        if ($handle === false) {
            throw CoverageUnavailable::because(sprintf('cannot read %s.', $this->file));
        }

        try {
            $areas = $this->readAreas($handle);
        } finally {
            fclose($handle);
        }

        try {
            return new Coverage(...$areas);
        } catch (InvalidArgumentException $exception) {
            throw CoverageUnavailable::because(sprintf('%s: %s', $this->file, $exception->getMessage()));
        }
    }

    public function save(Coverage $coverage): void
    {
        $lines = [implode(',', self::HEADER)];
        foreach ($coverage->areas() as $area) {
            $lines[] = implode(',', [
                (string) $area->code->value,
                self::field($area->name),
                (string) $area->municipality->value,
            ]);
        }

        $temporaryFile = $this->file . '.tmp';
        if (@file_put_contents($temporaryFile, implode("\n", $lines) . "\n") === false) {
            @unlink($temporaryFile);

            throw CoverageNotSaved::because(sprintf('cannot write %s.', $temporaryFile));
        }
        if (!@rename($temporaryFile, $this->file)) {
            @unlink($temporaryFile);

            throw CoverageNotSaved::because(sprintf('cannot move %s to %s.', $temporaryFile, $this->file));
        }
    }

    private static function field(string $value): string
    {
        if (strpbrk($value, ",\"\r\n") === false) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * @param resource $handle
     * @return list<CadastralArea>
     */
    private function readAreas($handle): array
    {
        $header = fgetcsv($handle, escape: '');
        if ($header !== self::HEADER) {
            throw CoverageUnavailable::because(sprintf('%s line 1: the header must be "%s".', $this->file, implode(',', self::HEADER)));
        }

        $areas = [];
        for ($line = 2; ($row = fgetcsv($handle, escape: '')) !== false; ++$line) {
            if ($row === [null]) {
                continue;
            }
            $areas[] = $this->area($row, $line);
        }

        return $areas;
    }

    /**
     * @param list<string|null> $row
     */
    private function area(array $row, int $line): CadastralArea
    {
        if (count($row) !== 3) {
            throw CoverageUnavailable::because(sprintf('%s line %d: expected 3 fields, found %d.', $this->file, $line, count($row)));
        }
        [$code, $name, $municipality] = $row;
        if (!ctype_digit((string) $code) || !ctype_digit((string) $municipality)) {
            throw CoverageUnavailable::because(sprintf('%s line %d: the codes must be numbers.', $this->file, $line));
        }

        try {
            return new CadastralArea(
                new CadastralAreaCode((int) $code),
                (string) $name,
                new MunicipalityCode((int) $municipality),
            );
        } catch (InvalidArgumentException $exception) {
            throw CoverageUnavailable::because(sprintf('%s line %d: %s', $this->file, $line, $exception->getMessage()));
        }
    }
}
