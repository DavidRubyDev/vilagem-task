<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** Kind, base number (kmenové číslo) and optional subdivision (poddělení), e.g. st. 80/2 or 867/121. */
final readonly class ParcelNumber
{
    public function __construct(
        public ParcelKind $kind,
        public int $baseNumber,
        public ?int $subdivision,
    ) {
        if ($baseNumber < 1) {
            throw InvalidParcelNumber::baseNumberNotPositive($baseNumber);
        }
        if ($subdivision !== null && $subdivision < 1) {
            throw InvalidParcelNumber::subdivisionNotPositive($subdivision);
        }
    }

    public function toString(): string
    {
        $number = $this->subdivision === null ? (string) $this->baseNumber : $this->baseNumber . '/' . $this->subdivision;

        return $this->kind === ParcelKind::Building ? 'st. ' . $number : $number;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->baseNumber === $other->baseNumber
            && $this->subdivision === $other->subdivision;
    }
}
