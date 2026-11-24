<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

use RuntimeException;

/**
 * A throwaway PHP built-in server on 127.0.0.1, so tests never need the network.
 */
final class LocalHttpServer
{
    /** @var resource|null */
    private $process;

    /**
     * @param resource $process
     */
    private function __construct(public readonly int $port, $process)
    {
        $this->process = $process;
    }

    /**
     * @param array<string, string> $environment added to the environment of the server process
     */
    public static function start(string $routerScript, array $environment = []): self
    {
        $port = self::freePort();
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $routerScript],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            env_vars: $environment === [] ? null : [...getenv(), ...$environment],
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the local HTTP server.');
        }

        $server = new self($port, $process);
        $server->waitUntilListening();

        return $server;
    }

    public function baseUrl(): string
    {
        return sprintf('http://127.0.0.1:%d/', $this->port);
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException('Cannot find a free port: ' . $errorMessage);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitUntilListening(): void
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $connection = @fsockopen('127.0.0.1', $this->port, timeout: 0.2);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(50_000);
        }

        $this->stop();

        throw new RuntimeException('The local HTTP server did not start.');
    }
}
