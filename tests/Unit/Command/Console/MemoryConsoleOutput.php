<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Command\Console;

use ParcelMap\Command\Console\ConsoleOutput;

final class MemoryConsoleOutput implements ConsoleOutput
{
    /** @var list<string> */
    public array $stdout = [];

    /** @var list<string> */
    public array $stderr = [];

    public function writeLine(string $line): void
    {
        $this->stdout[] = $line;
    }

    public function writeError(string $line): void
    {
        $this->stderr[] = $line;
    }
}
