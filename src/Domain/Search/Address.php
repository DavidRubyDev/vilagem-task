<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use ParcelMap\Domain\Map\Coordinates;

/** An address point (adresní místo): where a house number is, with the names that locate it. */
final readonly class Address
{
    public function __construct(
        public ?string $street,
        public string $municipalityPart,
        public string $municipality,
        public HouseNumber $number,
        public Coordinates $point,
    ) {
        if ($street !== null && trim($street) === '') {
            throw InvalidAddress::blankName('street');
        }
        if (trim($municipalityPart) === '') {
            throw InvalidAddress::blankName('municipality part');
        }
        if (trim($municipality) === '') {
            throw InvalidAddress::blankName('municipality');
        }
    }

    /** "Husova 624" with a street; without one the part of the municipality names the place, as in "Robousy 239". */
    public function label(): string
    {
        return ($this->street ?? $this->municipalityPart) . ' ' . $this->number->toString();
    }

    /** The municipality, prefixed by the part when the street does not say where in the municipality it is. */
    public function context(): string
    {
        if ($this->street !== null && !SearchText::of($this->municipalityPart)->equals(SearchText::of($this->municipality))) {
            return $this->municipalityPart . ', ' . $this->municipality;
        }

        return $this->municipality;
    }

    public function equals(self $other): bool
    {
        return $this->street === $other->street
            && $this->municipalityPart === $other->municipalityPart
            && $this->municipality === $other->municipality
            && $this->number->equals($other->number)
            && $this->point->equals($other->point);
    }
}
