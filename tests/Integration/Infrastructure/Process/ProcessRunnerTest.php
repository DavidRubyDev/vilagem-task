<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Integration\Infrastructure\Process;

use ParcelMap\Infrastructure\Process\ProcessNotStarted;
use ParcelMap\Infrastructure\Process\ProcessRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProcessRunnerTest extends TestCase
{
    #[Test]
    public function it_returns_exit_code_stdout_and_stderr_without_a_shell(): void
    {
        $result = new ProcessRunner()->run([PHP_BINARY, '-r', 'fwrite(STDOUT, "a b;c"); fwrite(STDERR, "err"); exit(3);']);

        self::assertSame(3, $result->exitCode);
        self::assertSame('a b;c', $result->stdout);
        self::assertSame('err', $result->stderr);
    }

    #[Test]
    public function it_reads_large_output_on_both_streams_without_blocking(): void
    {
        $code = 'fwrite(STDERR, str_repeat("e", 300000)); fwrite(STDOUT, str_repeat("o", 300000));';

        $result = new ProcessRunner()->run([PHP_BINARY, '-r', $code]);

        self::assertSame(300000, strlen($result->stdout));
        self::assertSame(300000, strlen($result->stderr));
    }

    #[Test]
    public function it_reports_a_program_that_cannot_be_started(): void
    {
        try {
            new ProcessRunner()->run(['parcel-map-no-such-program']);
            self::fail('Expected ProcessNotStarted.');
        } catch (ProcessNotStarted $e) {
            self::assertStringContainsString('parcel-map-no-such-program', $e->getMessage());
            self::assertStringContainsString('No such file or directory', $e->getMessage());
        }
    }
}
