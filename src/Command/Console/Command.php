<?php

declare(strict_types=1);

namespace ParcelMap\Command\Console;

interface Command
{
    public function name(): string;

    public function description(): string;

    /**
     * @param list<string> $arguments the arguments after the command name
     * @return int an ExitCode constant
     */
    public function run(array $arguments, ConsoleOutput $output): int;
}
