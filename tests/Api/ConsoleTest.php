<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    #[Test]
    public function it_lists_the_available_commands_and_exits_with_zero(): void
    {
        $result = ConsoleRunner::run([]);

        self::assertSame(0, $result->exitCode);
        self::assertStringStartsWith('Available commands:', $result->stdout);
    }

    #[Test]
    public function it_rejects_an_unknown_command_with_exit_code_two(): void
    {
        $result = ConsoleRunner::run(['nope']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('Unknown command "nope".', $result->stderr);
    }
}
