<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\ConvertedPlaces;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\PlaceConverter;

final class PlaceConverterUsingMemory implements PlaceConverter
{
    /** @var list<string> "<municipality> from <export path>" of every conversion, in order */
    public array $conversions = [];

    public function __construct(private readonly bool $failing = false) {}

    public function convert(DownloadedExport $export): ConvertedPlaces
    {
        if ($this->failing) {
            throw ConversionFailed::because('the fake place converter fails');
        }
        $this->conversions[] = sprintf('%d from %s', $export->municipality->value, $export->path);

        return new ConvertedPlaces($export->municipality, 'memory://places/' . $export->municipality->value);
    }
}
