<?php

declare(strict_types=1);

namespace ParcelMap\Application\Parcel;

use InvalidArgumentException;
use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Cadastre\ParcelNotFound;
use ParcelMap\Domain\Cadastre\ParcelRepository;
use ParcelMap\Domain\Cadastre\ParcelStoreNotPublished;
use ParcelMap\Domain\Cadastre\ParcelStoreUnreadable;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\InvalidTilesetVersion;
use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTilesetRepository;
use ParcelMap\Domain\Map\ParcelTilesetUnreadable;
use ParcelMap\Domain\Map\TilesetVersion;

final readonly class GetParcelDetailUseCase
{
    public function __construct(
        private ParcelTilesetRepository $tilesets,
        private ParcelRepository $parcels,
    ) {}

    /**
     * @throws ParcelDetailNotFound
     * @throws ParcelStoreUnreadable
     * @throws ParcelTilesetUnreadable
     */
    public function execute(ParcelDetailInput $input): ParcelDetailOutput
    {
        try {
            $tileset = $this->tilesets->current();
        } catch (ParcelDataNotImported) {
            throw ParcelDetailNotFound::noDataImported();
        }

        try {
            $requestedVersion = new TilesetVersion($input->version);
        } catch (InvalidTilesetVersion) {
            throw ParcelDetailNotFound::unknownVersion($input->version);
        }
        if (!$requestedVersion->equals($tileset->version)) {
            throw ParcelDetailNotFound::unknownVersion($input->version);
        }

        try {
            $id = new ParcelId($input->parcelId);
        } catch (InvalidArgumentException) {
            throw ParcelDetailNotFound::unknownParcel($input->parcelId);
        }

        try {
            $parcel = $this->parcels->get($tileset->version, $id);
        } catch (ParcelNotFound) {
            throw ParcelDetailNotFound::unknownParcel($input->parcelId);
        } catch (ParcelStoreNotPublished) {
            throw ParcelDetailNotFound::detailsNotImported($tileset->version->value);
        }

        return $this->output($parcel);
    }

    private function output(Parcel $parcel): ParcelDetailOutput
    {
        $area = $parcel->cadastralArea;

        return new ParcelDetailOutput(
            $parcel->id->value,
            $parcel->number->toString(),
            $parcel->number->kind === ParcelKind::Building ? 'building' : 'land',
            $area->code->value,
            $area->name,
            $area->municipality->code->value,
            $area->municipality->name,
            $parcel->landType->code,
            $parcel->landUse?->code,
            $parcel->area->squareMetres,
            array_map(
                static fn(array $ring): array => array_map(
                    static fn(Coordinates $position): array => [$position->longitude, $position->latitude],
                    $ring,
                ),
                $parcel->boundary->rings(),
            ),
            $parcel->cadastreViewerUrl(),
        );
    }
}
