<?php

declare(strict_types=1);

namespace ParcelMap\Application\Search;

use ParcelMap\Domain\Cadastre\ParcelKind;
use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\InvalidCoordinates;
use ParcelMap\Domain\Map\InvalidTilesetVersion;
use ParcelMap\Domain\Map\ParcelDataNotImported;
use ParcelMap\Domain\Map\ParcelTilesetRepository;
use ParcelMap\Domain\Map\ParcelTilesetUnreadable;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Search\InvalidSearchQuery;
use ParcelMap\Domain\Search\ParcelMatch;
use ParcelMap\Domain\Search\SearchIndexNotPublished;
use ParcelMap\Domain\Search\SearchIndexRepository;
use ParcelMap\Domain\Search\SearchIndexUnreadable;
use ParcelMap\Domain\Search\SearchQuery;

final readonly class SearchMapUseCase
{
    private const int PARCEL_LIMIT = 8;

    public function __construct(
        private ParcelTilesetRepository $tilesets,
        private SearchIndexRepository $index,
    ) {}

    /**
     * @throws SearchNotAvailable
     * @throws InvalidSearchRequest
     * @throws SearchIndexUnreadable
     * @throws ParcelTilesetUnreadable
     */
    public function execute(SearchMapInput $input): SearchMapOutput
    {
        $version = $this->currentVersion($input->version);
        try {
            $query = SearchQuery::parse($input->query);
        } catch (InvalidSearchQuery $exception) {
            throw InvalidSearchRequest::invalidQuery($exception->getMessage());
        }
        $near = $this->near($input);

        try {
            $parcels = $query->searchesParcels() ? $this->index->findParcels($version, $query, $near, self::PARCEL_LIMIT) : [];
        } catch (SearchIndexNotPublished) {
            throw SearchNotAvailable::indexNotPublished($version->value);
        }

        return new SearchMapOutput([], array_map($this->parcelOutput(...), $parcels));
    }

    private function currentVersion(string $requested): TilesetVersion
    {
        try {
            $tileset = $this->tilesets->current();
        } catch (ParcelDataNotImported) {
            throw SearchNotAvailable::noDataImported();
        }

        try {
            $version = new TilesetVersion($requested);
        } catch (InvalidTilesetVersion) {
            throw SearchNotAvailable::unknownVersion($requested);
        }
        if (!$version->equals($tileset->version)) {
            throw SearchNotAvailable::unknownVersion($requested);
        }

        return $version;
    }

    private function near(SearchMapInput $input): ?Coordinates
    {
        if ($input->nearLongitude === null && $input->nearLatitude === null) {
            return null;
        }
        if ($input->nearLongitude === null || $input->nearLatitude === null) {
            throw InvalidSearchRequest::invalidNear('longitude and latitude must be given together.');
        }

        try {
            return new Coordinates($input->nearLongitude, $input->nearLatitude);
        } catch (InvalidCoordinates $exception) {
            throw InvalidSearchRequest::invalidNear($exception->getMessage());
        }
    }

    private function parcelOutput(ParcelMatch $match): ParcelResultOutput
    {
        $area = $match->cadastralArea;

        return new ParcelResultOutput(
            $match->id->value,
            $match->number->toString(),
            $match->number->kind === ParcelKind::Building ? 'building' : 'land',
            $area->code->value,
            $area->name,
            $area->municipality->code->value,
            $area->municipality->name,
        );
    }
}
