<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelNotFound;
use ParcelMap\Domain\Cadastre\ParcelRepository;
use ParcelMap\Domain\Cadastre\ParcelStoreNotPublished;
use ParcelMap\Domain\Map\TilesetVersion;

final readonly class ParcelRepositoryUsingMemory implements ParcelRepository
{
    /** @var array<int, Parcel> by parcel id */
    private array $parcels;

    /**
     * @param TilesetVersion|null $publishedVersion the version that has a store; null when there is none
     */
    public function __construct(
        private ?TilesetVersion $publishedVersion,
        Parcel ...$parcels,
    ) {
        $byId = [];
        foreach ($parcels as $parcel) {
            $byId[$parcel->id->value] = $parcel;
        }
        $this->parcels = $byId;
    }

    public function get(TilesetVersion $version, ParcelId $id): Parcel
    {
        if ($this->publishedVersion === null || !$this->publishedVersion->equals($version)) {
            throw ParcelStoreNotPublished::forVersion($version->value);
        }

        return $this->parcels[$id->value] ?? throw ParcelNotFound::withId($id->value);
    }
}
