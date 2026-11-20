<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

final readonly class CadastralAreaCode
{
    public function __construct(public int $value)
    {
        if ($value < 100000 || $value > 999999) {
            throw InvalidCadastralAreaCode::notSixDigits($value);
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
