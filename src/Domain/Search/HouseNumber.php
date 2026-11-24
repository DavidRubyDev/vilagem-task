<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

/** A house number in Czech address notation: 624, 624/3, 624/3a (číslo popisné) or č. ev. 12 (číslo evidenční). */
final readonly class HouseNumber
{
    public function __construct(
        public HouseNumberKind $kind,
        public int $number,
        public ?int $orientationNumber,
        public ?string $orientationLetter,
    ) {
        if ($number < 1) {
            throw InvalidHouseNumber::notPositive($number);
        }
        if ($orientationNumber !== null && $orientationNumber < 1) {
            throw InvalidHouseNumber::orientationNotPositive($orientationNumber);
        }
        if ($orientationLetter !== null && preg_match('~^[A-Za-z]$~', $orientationLetter) !== 1) {
            throw InvalidHouseNumber::invalidLetter($orientationLetter);
        }
        if ($orientationLetter !== null && $orientationNumber === null) {
            throw InvalidHouseNumber::letterWithoutOrientation();
        }
    }

    public function toString(): string
    {
        $text = (string) $this->number;
        if ($this->orientationNumber !== null) {
            $text .= '/' . $this->orientationNumber . ($this->orientationLetter ?? '');
        }

        return $this->kind === HouseNumberKind::Registration ? 'č. ev. ' . $text : $text;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->number === $other->number
            && $this->orientationNumber === $other->orientationNumber
            && $this->orientationLetter === $other->orientationLetter;
    }
}
