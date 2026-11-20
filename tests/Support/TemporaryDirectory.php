<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Support;

use RuntimeException;

final class TemporaryDirectory
{
    private function __construct(public readonly string $path) {}

    public static function create(): self
    {
        $path = sys_get_temp_dir() . '/parcel-map-test-' . bin2hex(random_bytes(6));
        if (!mkdir($path, 0o777, true)) {
            throw new RuntimeException('Cannot create ' . $path);
        }

        return new self($path);
    }

    public function remove(): void
    {
        self::removeRecursively($this->path);
    }

    private static function removeRecursively(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeRecursively($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
