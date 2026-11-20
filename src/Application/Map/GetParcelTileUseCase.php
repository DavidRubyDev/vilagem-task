<?php

declare(strict_types=1);

namespace ParcelMap\Application\Map;

use ParcelMap\Domain\Map\InvalidTileCoordinate;
use ParcelMap\Domain\Map\InvalidTilesetVersion;
use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTilesetRepository;
use ParcelMap\Domain\Map\TileCoordinate;
use ParcelMap\Domain\Map\TilesetVersion;

final readonly class GetParcelTileUseCase
{
    public function __construct(private ParcelTilesetRepository $tilesets) {}

    /**
     * @throws ParcelTileNotFound
     */
    public function execute(ParcelTileInput $input): ParcelTileOutput
    {
        try {
            $tileset = $this->tilesets->current();
            $requestedVersion = new TilesetVersion($input->version);
        } catch (ParcelDataNotImported) {
            throw ParcelTileNotFound::noDataImported();
        } catch (InvalidTilesetVersion) {
            throw ParcelTileNotFound::unknownVersion($input->version);
        }
        if (!$requestedVersion->equals($tileset->version)) {
            throw ParcelTileNotFound::unknownVersion($input->version);
        }

        try {
            $coordinate = new TileCoordinate($input->zoom, $input->x, $input->y);
        } catch (InvalidTileCoordinate) {
            throw ParcelTileNotFound::invalidCoordinate($input->zoom, $input->x, $input->y);
        }
        if (!$tileset->coversZoom($coordinate->zoom)) {
            return new ParcelTileOutput(true, '');
        }

        $tile = $this->tilesets->tile($coordinate);

        return new ParcelTileOutput($tile->isEmpty(), $tile->gzippedData());
    }
}
