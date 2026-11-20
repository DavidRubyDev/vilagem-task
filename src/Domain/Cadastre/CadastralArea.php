<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** A cadastral area (katastrální území); identity is its code. */
final readonly class CadastralArea
{
    public function __construct(
        public CadastralAreaCode $code,
        public string $name,
        public MunicipalityCode $municipality,
    ) {
        if (trim($name) === '') {
            throw InvalidCadastralArea::emptyName($code->value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->code->equals($other->code);
    }
}
