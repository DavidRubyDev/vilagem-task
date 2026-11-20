<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Command\Console;

use ParcelMap\Command\Console\Command;
use ParcelMap\Command\Console\ConsoleOutput;
use Throwable;

final class StubCommand implements Command
{
    /** @var list<string> */
    public array $receivedArguments = [];

    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly int $exitCode = 0,
        private readonly ?Throwable $failure = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function run(array $arguments, ConsoleOutput $output): int
    {
        $this->receivedArguments = $arguments;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->exitCode;
    }
}
