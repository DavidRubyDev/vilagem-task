<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class CadastralAreaNotInExport extends ImportFailed
{
    public static function for(int $area, int $municipality, string $date): self
    {
        return new self(sprintf(
            'The cadastral area %d has no parcels in the RÚIAN export of %s for municipality %d.',
            $area,
            $date,
            $municipality,
        ));
    }
}
