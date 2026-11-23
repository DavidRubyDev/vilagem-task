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
        $municipalityNames = [];
        foreach ($areas as $area) {
            if (isset($seen[$area->code->value])) {
                throw InvalidCoverage::duplicateArea($area->code->value);
            }
            $seen[$area->code->value] = true;

            $municipality = $area->municipality;
            $municipalityNames[$municipality->code->value] ??= $municipality->name;
            if ($municipalityNames[$municipality->code->value] !== $municipality->name) {
                throw InvalidCoverage::inconsistentMunicipality($municipality->code->value);
            }
        }

        $this->areas = array_values($areas);
    }

    public static function sortedByCode(CadastralArea ...$areas): self
    {
        usort($areas, static fn(CadastralArea $a, CadastralArea $b): int => $a->code->value <=> $b->code->value);

        return new self(...$areas);
    }

    /**
     * @return non-empty-list<CadastralArea>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    /**
     * @throws CadastralAreaNotCovered
     */
    public function area(CadastralAreaCode $code): CadastralArea
    {
        foreach ($this->areas as $area) {
            if ($area->code->equals($code)) {
                return $area;
            }
        }

        throw CadastralAreaNotCovered::code($code->value);
    }

    /**
     * @return non-empty-list<CadastralArea> the covered areas of the municipality, in coverage order
     * @throws MunicipalityNotCovered
     */
    public function areasOf(MunicipalityCode $municipality): array
    {
        $areas = array_values(array_filter(
            $this->areas,
            static fn(CadastralArea $area): bool => $area->municipality->code->equals($municipality),
        ));
        if ($areas === []) {
            throw MunicipalityNotCovered::code($municipality->value);
        }

        return $areas;
    }

    /**
     * @return non-empty-list<MunicipalityCode>
     */
    public function municipalities(): array
    {
        $municipalities = [];
        foreach ($this->areas as $area) {
            $municipalities[$area->municipality->code->value] ??= $area->municipality->code;
        }

        return array_values($municipalities);
    }
}
