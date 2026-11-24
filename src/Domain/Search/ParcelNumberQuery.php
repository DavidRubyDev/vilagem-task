<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Search;

use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNumber;

/** A parcel number as searched: base, optional subdivision, optional kind (null = land and building plots). */
final readonly class ParcelNumberQuery
{
    private const int LARGEST_BASE_NUMBER = 99999;
    private const int LARGEST_SUBDIVISION = 9999;

    public function __construct(
        public ?ParcelKind $kind,
        public int $baseNumber,
        public ?int $subdivision,
    ) {
        if ($baseNumber < 1 || $baseNumber > self::LARGEST_BASE_NUMBER) {
            throw InvalidParcelNumberQuery::baseNumberOutOfRange($baseNumber);
        }
        if ($subdivision !== null && ($subdivision < 1 || $subdivision > self::LARGEST_SUBDIVISION)) {
            throw InvalidParcelNumberQuery::subdivisionOutOfRange($subdivision);
        }
    }

    public function matches(ParcelNumber $number): bool
    {
        return $number->baseNumber === $this->baseNumber
            && $number->subdivision === $this->subdivision
            && ($this->kind === null || $number->kind === $this->kind);
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->baseNumber === $other->baseNumber
            && $this->subdivision === $other->subdivision;
    }
}
