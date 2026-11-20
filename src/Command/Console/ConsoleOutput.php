<?php

declare(strict_types=1);

namespace ParcelMap\Command\Console;

interface ConsoleOutput
{
    public function writeLine(string $line): void;

    public function writeError(string $line): void;
}
