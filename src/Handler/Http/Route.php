<?php

declare(strict_types=1);

namespace ParcelMap\Handler\Http;

/**
 * A method and path handled by one handler. The path may contain {name} placeholders; each matches one or more
 * letters, digits, "_" or "-" (so never "/" or "."). All other characters are literal.
 */
final readonly class Route
{
    private string $pattern;

    public function __construct(
        public string $method,
        public string $path,
        public RequestHandler $handler,
    ) {
        $this->pattern = '#^' . preg_replace_callback(
            '/\{([A-Za-z]+)\}|[^{]+|\{/',
            static fn(array $part): string => isset($part[1])
                ? '(?P<' . $part[1] . '>[A-Za-z0-9_-]+)'
                : preg_quote($part[0], '#'),
            $path,
        ) . '$#D';
    }

    public function matches(string $path): bool
    {
        return preg_match($this->pattern, $path) === 1;
    }

    /**
     * @return array<string, string> the placeholder values; empty when the path does not match
     */
    public function parametersOf(string $path): array
    {
        if (preg_match($this->pattern, $path, $matches) !== 1) {
            return [];
        }

        $parameters = [];
        foreach ($matches as $name => $value) {
            if (is_string($name)) {
                $parameters[$name] = $value;
            }
        }

        return $parameters;
    }
}
