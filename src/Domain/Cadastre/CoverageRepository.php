<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

interface CoverageRepository
{
    /**
     * @throws CoverageUnavailable
     */
    public function get(): Coverage;

    /**
     * @throws CoverageNotSaved
     */
    public function save(Coverage $coverage): void;
}
