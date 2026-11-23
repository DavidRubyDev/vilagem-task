<?php

declare(strict_types=1);

namespace ParcelMap\Application\Import;

/** A parcel store file the builder produced; the path is opaque to the use case. */
final readonly class BuiltParcelStore
{
    /**
     * @param list<UnknownCode> $unknownCodes sorted by code list (land type first), then code
     */
    public function __construct(
        public string $path,
        public array $unknownCodes,
    ) {}
}
