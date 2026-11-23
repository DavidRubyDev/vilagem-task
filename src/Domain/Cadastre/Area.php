<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** The parcel area (výměra) in square metres. */
final readonly class Area
{
    public function __construct(public int $squareMetres)
    {
        if ($squareMetres < 1) {
            throw InvalidArea::notPositive($squareMetres);
        }
    }

    public function equals(self $other): bool
    {
        return $this->squareMetres === $other->squareMetres;
    }
}
