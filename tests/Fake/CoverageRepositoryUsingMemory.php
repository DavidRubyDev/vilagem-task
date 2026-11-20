<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Domain\Cadastre\Coverage;
use ParcelMap\Domain\Cadastre\CoverageNotSaved;
use ParcelMap\Domain\Cadastre\CoverageRepository;
use ParcelMap\Domain\Cadastre\CoverageUnavailable;

final class CoverageRepositoryUsingMemory implements CoverageRepository
{
    /** The coverage of the last successful save(), null when nothing was saved */
    public ?Coverage $saved = null;

    public function __construct(
        private readonly Coverage|CoverageUnavailable|null $coverage = null,
        private readonly ?CoverageNotSaved $saveFailure = null,
    ) {}

    public function get(): Coverage
    {
        if ($this->coverage instanceof CoverageUnavailable) {
            throw $this->coverage;
        }

        return $this->coverage ?? throw CoverageUnavailable::because('the fake repository holds no coverage');
    }

    public function save(Coverage $coverage): void
    {
        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }
        $this->saved = $coverage;
    }
}
