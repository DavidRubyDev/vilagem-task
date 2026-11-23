<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

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
}
