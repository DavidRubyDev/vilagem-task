<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Cadastre;

/** The cadastral areas the map covers. */
final readonly class Coverage
{
    /** @var non-empty-list<CadastralArea> */
    private array $areas;

    public function __construct(CadastralArea ...$areas)
    {
        if ($areas === []) {
            throw InvalidCoverage::empty();
        }

        $seen = [];
        foreach ($areas as $area) {
            if (isset($seen[$area->code->value])) {
                throw InvalidCoverage::duplicateArea($area->code->value);
            }
            $seen[$area->code->value] = true;
        }

        $this->areas = array_values($areas);
    }

    /**
     * @return non-empty-list<CadastralArea>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    /**
     * @return non-empty-list<MunicipalityCode>
     */
    public function municipalities(): array
    {
        $municipalities = [];
        foreach ($this->areas as $area) {
            $municipalities[$area->municipality->value] ??= $area->municipality;
        }

        return array_values($municipalities);
    }
}
