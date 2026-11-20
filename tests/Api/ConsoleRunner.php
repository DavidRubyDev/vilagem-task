<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use RuntimeException;

/**
 * Runs bin/console as a subprocess, as an operator would.
 */
final class ConsoleRunner
{
    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment added to the inherited environment
     */
    public static function run(array $arguments, array $environment = []): ConsoleResult
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/bin/console', ...$arguments];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            [...getenv(), ...$environment],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start bin/console.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return new ConsoleResult(proc_close($process), $stdout, $stderr);
    }
}
