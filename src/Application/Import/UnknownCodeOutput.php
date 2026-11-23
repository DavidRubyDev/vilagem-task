<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

final readonly class UnknownCodeOutput
{
    /**
     * @param 'land type'|'land use' $codeList
     */
    public function __construct(
        public string $codeList,
        public int $code,
        public int $parcelCount,
    ) {}
}
