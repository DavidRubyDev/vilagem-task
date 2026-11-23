<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** Building or land plot; the value is the RÚIAN code DruhCislovaniKod (1 stavební, 2 pozemková). */
enum ParcelKind: int
{
    case Building = 1;
    case Land = 2;

    public static function fromCode(int $code): self
    {
        return self::tryFrom($code) ?? throw InvalidParcelKind::unknownCode($code);
    }
}
