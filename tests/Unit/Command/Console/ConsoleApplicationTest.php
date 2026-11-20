<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Unit\Command\Console;

use ParcelMap\Command\Console\ConsoleApplication;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConsoleApplicationTest extends TestCase
{
    #[Test]
    public function it_lists_the_commands_with_name_and_description_when_called_without_arguments(): void
    {
        $output = new MemoryConsoleOutput();
        $application = new ConsoleApplication(
            new StubCommand('first:run', 'Does the first thing.'),
            new StubCommand('second:run', 'Does the second thing.'),
        );

        $exitCode = $application->run([], $output);

        self::assertSame(0, $exitCode);
        self::assertSame(
            ['Available commands:', '  first:run  Does the first thing.', '  second:run  Does the second thing.'],
            $output->stdout,
        );
        self::assertSame([], $output->stderr);
    }

    #[Test]
    public function it_runs_the_named_command_with_the_remaining_arguments_and_returns_its_exit_code(): void
    {
        $command = new StubCommand('first:run', 'Does the first thing.', exitCode: 7);
        $application = new ConsoleApplication(new StubCommand('other', 'Other.'), $command);

        $exitCode = $application->run(['first:run', '--a=1', 'b'], new MemoryConsoleOutput());

        self::assertSame(7, $exitCode);
        self::assertSame(['--a=1', 'b'], $command->receivedArguments);
    }

    #[Test]
    public function it_reports_an_unknown_command_and_lists_the_commands_with_exit_code_two(): void
    {
        $output = new MemoryConsoleOutput();
        $application = new ConsoleApplication(new StubCommand('first:run', 'Does the first thing.'));

        $exitCode = $application->run(['nope'], $output);

        self::assertSame(2, $exitCode);
        self::assertSame(['Unknown command "nope".'], array_slice($output->stderr, 0, 1));
        self::assertContains('  first:run  Does the first thing.', [...$output->stdout, ...$output->stderr]);
    }

    #[Test]
    public function it_reports_an_uncaught_exception_with_exit_code_one(): void
    {
        $output = new MemoryConsoleOutput();
        $application = new ConsoleApplication(
            new StubCommand('boom', 'Fails.', failure: new RuntimeException('it broke')),
        );

        $exitCode = $application->run(['boom'], $output);

        self::assertSame(1, $exitCode);
        self::assertSame('Unexpected error: it broke', $output->stderr[0]);
        self::assertStringContainsString('RuntimeException', implode("\n", $output->stderr));
    }
}
