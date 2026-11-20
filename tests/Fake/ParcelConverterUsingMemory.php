<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Application\Import\AreaParcelCount;
use ParcelMap\Application\Import\ConversionFailed;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\DownloadedExport;
use ParcelMap\Application\Import\ParcelConverter;
use ParcelMap\Domain\Cadastre\CadastralArea;

final class ParcelConverterUsingMemory implements ParcelConverter
{
    /** @var list<string> "<municipality>: <area codes> from <export path>" of every conversion, in order */
    public array $conversions = [];

    /**
     * @param array<int, int> $parcelCounts parcels per cadastral area code
     */
    public function __construct(
        private readonly array $parcelCounts,
        private readonly bool $failing = false,
    ) {}

    public function convert(DownloadedExport $export, array $areas): ConvertedParcels
    {
        if ($this->failing) {
            throw ConversionFailed::because('the fake converter fails');
        }
        $this->conversions[] = sprintf(
            '%d: %s from %s',
            $export->municipality->value,
            implode(',', array_map(static fn(CadastralArea $area): int => $area->code->value, $areas)),
            $export->path,
        );

        return new ConvertedParcels(
            $export->municipality,
            'memory://municipalities/' . $export->municipality->value,
            array_map(
                fn(CadastralArea $area): AreaParcelCount => new AreaParcelCount($area->code, $this->parcelCounts[$area->code->value]),
                $areas,
            ),
        );
    }
}
