<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** An https URL with {z}, {x} and {y} placeholders. */
final readonly class TileUrlTemplate
{
    private const array PLACEHOLDERS = ['{z}', '{x}', '{y}'];

    public function __construct(public string $value)
    {
        if (!str_starts_with($value, 'https://')) {
            throw InvalidTileUrlTemplate::notHttps($value);
        }
        foreach (self::PLACEHOLDERS as $placeholder) {
            if (!str_contains($value, $placeholder)) {
                throw InvalidTileUrlTemplate::missingPlaceholder($value, $placeholder);
            }
        }
        if (filter_var(str_replace(self::PLACEHOLDERS, '0', $value), FILTER_VALIDATE_URL) === false) {
            throw InvalidTileUrlTemplate::notAUrl($value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
