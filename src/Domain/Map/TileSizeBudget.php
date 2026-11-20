<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** The largest gzip-compressed size, in bytes, a vector tile may have. */
final readonly class TileSizeBudget
{
    private const int SMOOTH_MAP_BYTES = 200_000;

    public function __construct(public int $maxBytes)
    {
        if ($maxBytes < 1) {
            throw InvalidTileSizeBudget::notPositive($maxBytes);
        }
    }

    /**
     * At most about 20 tiles are visible on a 1920x1080 screen, so with this budget a view stays under 4 MB,
     * which MapLibre parses and draws smoothly.
     */
    public static function smoothMap(): self
    {
        return new self(self::SMOOTH_MAP_BYTES);
    }

    public function allows(int $bytes): bool
    {
        return $bytes <= $this->maxBytes;
    }

    public function equals(self $other): bool
    {
        return $this->maxBytes === $other->maxBytes;
    }
}
