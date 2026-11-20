<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageRepository;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;

final readonly class CoverageRepositoryUsingMemory implements CoverageRepository
{
    public function __construct(private Coverage|CoverageUnavailable $coverage) {}

    public function get(): Coverage
    {
        if ($this->coverage instanceof CoverageUnavailable) {
            throw $this->coverage;
        }

        return $this->coverage;
    }
}
