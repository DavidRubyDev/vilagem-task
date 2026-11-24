<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** A search index file the builder produced; the path is opaque to the use case. */
final readonly class BuiltSearchIndex
{
    public function __construct(public string $path) {}
}
