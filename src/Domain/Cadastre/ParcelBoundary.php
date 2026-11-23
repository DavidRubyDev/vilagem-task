<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

use ParcelMap\Domain\Map\Bounds;
use ParcelMap\Domain\Map\Coordinates;

/** The parcel polygon (hranice parcely) in WGS84: the outer ring first, then the holes. */
final readonly class ParcelBoundary
{
    /**
     * @param non-empty-list<non-empty-list<Coordinates>> $rings
     */
    private function __construct(private array $rings) {}

    /**
     * @param list<list<Coordinates>> $rings the outer ring first, then holes
     * @throws InvalidParcelBoundary
     */
    public static function fromRings(array $rings): self
    {
        if ($rings === []) {
            throw InvalidParcelBoundary::noRings();
        }

        $checked = [];
        foreach ($rings as $index => $ring) {
            if (count($ring) < 4) {
                throw InvalidParcelBoundary::ringTooShort($index);
            }
            if (!$ring[0]->equals($ring[array_key_last($ring)])) {
                throw InvalidParcelBoundary::ringNotClosed($index);
            }
            $checked[] = $ring;
        }

        return new self($checked);
    }

    /**
     * @return non-empty-list<non-empty-list<Coordinates>>
     */
    public function rings(): array
    {
        return $this->rings;
    }

    /** The rectangle around the outer ring; holes lie inside it. */
    public function bounds(): Bounds
    {
        $longitudes = array_map(static fn(Coordinates $position): float => $position->longitude, $this->rings[0]);
        $latitudes = array_map(static fn(Coordinates $position): float => $position->latitude, $this->rings[0]);

        return new Bounds(
            new Coordinates(min($longitudes), min($latitudes)),
            new Coordinates(max($longitudes), max($latitudes)),
        );
    }
}
