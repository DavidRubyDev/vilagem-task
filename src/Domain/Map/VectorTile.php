<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** The content of one vector tile, stored gzip-compressed as MBTiles keeps it; or no tile at all. */
final readonly class VectorTile
{
    private function __construct(private string $gzippedData) {}

    public static function fromGzippedData(string $data): self
    {
        if (!str_starts_with($data, "\x1f\x8b")) {
            throw InvalidVectorTile::notGzipped();
        }

        return new self($data);
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function isEmpty(): bool
    {
        return $this->gzippedData === '';
    }

    /** An empty string when the tile is empty. */
    public function gzippedData(): string
    {
        return $this->gzippedData;
    }
}
