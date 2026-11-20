<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final readonly class ImportParcelsInput
{
    /**
     * @param string|null $exportDate Y-m-d, or null for the latest published monthly export
     */
    public function __construct(public ?string $exportDate) {}
}
