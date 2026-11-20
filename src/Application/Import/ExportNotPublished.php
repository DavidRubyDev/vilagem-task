<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final class ExportNotPublished extends ImportFailed
{
    public static function for(int $municipality, string $date): self
    {
        return new self(sprintf('The RÚIAN export of %s for municipality %d is not published.', $date, $municipality));
    }
}
