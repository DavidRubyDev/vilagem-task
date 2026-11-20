<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ParcelConverter;
use ParcelMap\Domain\Cadastre\CadastralArea;

final class ParcelConverterUsingMemory implements ParcelConverter
{
    /** @var list<string> "<area> from <export path>" of every conversion, in order */
    public array $conversions = [];

    /**
     * @param array<int, int> $parcelCounts parcels per cadastral area code
     */
    public function __construct(
        private readonly array $parcelCounts,
        private readonly bool $failing = false,
    ) {}

    public function convert(DownloadedExport $export, CadastralArea $area): ConvertedParcels
    {
        if ($this->failing) {
            throw ConversionFailed::because('the fake converter fails');
        }
        $this->conversions[] = $area->code->value . ' from ' . $export->path;

        return new ConvertedParcels($area->code, 'memory://parcels/' . $area->code->value, $this->parcelCounts[$area->code->value]);
    }
}
