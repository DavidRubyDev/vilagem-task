<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

use InvalidArgumentException;
use OutOfBoundsException;
use ParcelMap\Domain\Cadastre\CadastralAreaCode;
use ParcelMap\Domain\Cadastre\MunicipalityCode;

/** The parcels of the covered cadastral areas of one municipality converted for the tile builder; the path is opaque to the use case. */
final readonly class ConvertedParcels
{
    /** @var non-empty-list<AreaParcelCount> */
    private array $areaCounts;

    /**
     * @param list<AreaParcelCount> $areaCounts
     */
    public function __construct(
        public MunicipalityCode $municipality,
        public string $path,
        array $areaCounts,
    ) {
        if ($areaCounts === []) {
            throw new InvalidArgumentException('Converted parcels must cover at least one cadastral area.');
        }

        $seen = [];
        foreach ($areaCounts as $count) {
            if (isset($seen[$count->area->value])) {
                throw new InvalidArgumentException(sprintf('The cadastral area %d is counted more than once.', $count->area->value));
            }
            $seen[$count->area->value] = true;
        }

        $this->areaCounts = $areaCounts;
    }

    /**
     * @throws OutOfBoundsException for an area that was not converted (a contract violation by the adapter)
     */
    public function parcelCountOf(CadastralAreaCode $area): int
    {
        foreach ($this->areaCounts as $count) {
            if ($count->area->equals($area)) {
                return $count->parcelCount;
            }
        }

        throw new OutOfBoundsException(sprintf('The cadastral area %d was not converted.', $area->value));
    }

    public function totalParcelCount(): int
    {
        return array_sum(array_map(static fn(AreaParcelCount $count): int => $count->parcelCount, $this->areaCounts));
    }
}
