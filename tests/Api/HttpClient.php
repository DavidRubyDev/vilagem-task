<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Api;

use CurlHandle;
use RuntimeException;

/**
 * Minimal real-HTTP client for API tests. Uses curl because $http_response_header is deprecated in PHP 8.6.
 */
final readonly class HttpClient
{
    public function __construct(private string $baseUrl) {}

    public static function fromEnvironment(): self
    {
        $url = getenv('APP_URL');

        return new self($url === false || $url === '' ? 'http://app:8000' : $url);
    }

    /**
     * @param non-empty-string $method
     * @param array<string, string> $requestHeaders
     */
    public function request(string $method, string $path, array $requestHeaders = []): HttpResult
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Cannot initialise curl.');
        }

        $headers = [];
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            // Without CURLOPT_ENCODING curl neither asks for nor decodes compressed bodies, so tests see the raw bytes.
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $name, string $value): string => $name . ': ' . $value,
                array_keys($requestHeaders),
                $requestHeaders,
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        $body = curl_exec($handle);
        if (!is_string($body)) {
            $error = curl_error($handle);
            throw new RuntimeException(
                sprintf('The app is not reachable at %s (%s). Run `make up-app` first.', $this->baseUrl, $error),
            );
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResult($status, $headers, $body);
    }
}
