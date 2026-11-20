<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

use DateTimeImmutable;
use DateTimeZone;

/** Identifies one published parcel tileset; part of the tile URLs so a new import gets new URLs. */
final readonly class TilesetVersion
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[A-Za-z0-9-]{1,32}$/', $value) !== 1) {
            throw InvalidTilesetVersion::malformed($value);
        }
    }

    public static function fromTime(DateTimeImmutable $time): self
    {
        return new self($time->setTimezone(new DateTimeZone('UTC'))->format('YmdHis'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
