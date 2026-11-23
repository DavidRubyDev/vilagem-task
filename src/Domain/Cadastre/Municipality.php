<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** A municipality (obec); identity is its code. */
final readonly class Municipality
{
    public function __construct(
        public MunicipalityCode $code,
        public string $name,
    ) {
        if (trim($name) === '') {
            throw InvalidMunicipality::emptyName($code->value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->code->equals($other->code);
    }
}
