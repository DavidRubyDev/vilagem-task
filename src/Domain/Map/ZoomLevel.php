<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** Web map zoom as MapLibre understands it; fractions are allowed. */
final readonly class ZoomLevel
{
    public function __construct(public float $value)
    {
        if (!is_finite($value) || $value < 0.0 || $value > 24.0) {
            throw InvalidZoomLevel::outOfRange($value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
