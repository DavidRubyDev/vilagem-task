<?php

declare(strict_types=1);

namespace ParcelMap\Command\Console;

use Throwable;

final readonly class ConsoleApplication
{
    /** @var list<Command> */
    private array $commands;

    public function __construct(Command ...$commands)
    {
        $this->commands = array_values($commands);
    }

    /**
     * @param list<string> $arguments the arguments without the script name
     */
    public function run(array $arguments, ConsoleOutput $output): int
    {
        if ($arguments === []) {
            $this->listCommands($output->writeLine(...));

            return ExitCode::SUCCESS;
        }

        $name = $arguments[0];
        foreach ($this->commands as $command) {
            if ($command->name() === $name) {
                return $this->runCommand($command, array_slice($arguments, 1), $output);
            }
        }

        $output->writeError(sprintf('Unknown command "%s".', $name));
        $this->listCommands($output->writeError(...));

        return ExitCode::USAGE;
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(Command $command, array $arguments, ConsoleOutput $output): int
    {
        try {
            return $command->run($arguments, $output);
        } catch (Throwable $throwable) {
            $output->writeError('Unexpected error: ' . $throwable->getMessage());
            $output->writeError((string) $throwable);

            return ExitCode::FAILURE;
        }
    }

    /**
     * @param callable(string): void $write
     */
    private function listCommands(callable $write): void
    {
        $write('Available commands:');
        foreach ($this->commands as $command) {
            $write(sprintf('  %s  %s', $command->name(), $command->description()));
        }
    }
}
