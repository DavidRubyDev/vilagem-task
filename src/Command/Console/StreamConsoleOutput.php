<?php

declare(strict_types=1);

namespace ParcelMap\Command\Console;

final class StreamConsoleOutput implements ConsoleOutput
{
    public function writeLine(string $line): void
    {
        fwrite(STDOUT, $line . "\n");
    }

    public function writeError(string $line): void
    {
        fwrite(STDERR, $line . "\n");
    }
}
