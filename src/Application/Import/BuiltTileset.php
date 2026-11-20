<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** A tileset file the builder produced; the path is opaque to the use case. */
final readonly class BuiltTileset
{
    public function __construct(public string $path) {}
}
