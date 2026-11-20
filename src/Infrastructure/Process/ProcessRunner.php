<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Process;

use RuntimeException;

/** Runs an external program from an argument array (no shell) and collects its output. */
final readonly class ProcessRunner
{
    /**
     * @param non-empty-list<string> $command
     * @throws RuntimeException when the program cannot be started
     */
    public function run(array $command): ProcessResult
    {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start ' . $command[0] . '.');
        }

        [$stdout, $stderr] = $this->readBoth($pipes[1], $pipes[2]);

        return new ProcessResult(proc_close($process), $stdout, $stderr);
    }

    /**
     * Reads both pipes together so a program that fills one of them cannot block on the other.
     *
     * @param resource $out
     * @param resource $err
     * @return array{string, string}
     */
    private function readBoth($out, $err): array
    {
        $buffers = [(int) $out => '', (int) $err => ''];
        $open = [(int) $out => $out, (int) $err => $err];

        while ($open !== []) {
            $read = array_values($open);
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 65536);
                if ($chunk === false || ($chunk === '' && feof($stream))) {
                    fclose($stream);
                    unset($open[(int) $stream]);

                    continue;
                }
                $buffers[(int) $stream] .= $chunk;
            }
        }

        return [$buffers[(int) $out], $buffers[(int) $err]];
    }
}
