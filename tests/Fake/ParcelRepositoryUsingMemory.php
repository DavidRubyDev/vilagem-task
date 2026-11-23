<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Domain\Cadastre\Parcel;
use ParcelMap\Domain\Cadastre\ParcelId;
use ParcelMap\Domain\Cadastre\ParcelNotFound;
use ParcelMap\Domain\Cadastre\ParcelRepository;
use ParcelMap\Domain\Cadastre\ParcelStoreNotPublished;
use ParcelMap\Domain\Cadastre\ParcelStoreUnreadable;
use ParcelMap\Domain\Map\TilesetVersion;

final readonly class ParcelRepositoryUsingMemory implements ParcelRepository
{
    /** @var array<int, Parcel> by parcel id */
    private array $parcels;

    /**
     * @param TilesetVersion|null $publishedVersion the version that has a store; null when there is none
     * @param list<Parcel> $parcels
     * @param bool $unreadable the store exists but cannot be read: get() throws ParcelStoreUnreadable
     */
    public function __construct(
        private ?TilesetVersion $publishedVersion,
        array $parcels = [],
        private bool $unreadable = false,
    ) {
        $byId = [];
        foreach ($parcels as $parcel) {
            $byId[$parcel->id->value] = $parcel;
        }
        $this->parcels = $byId;
    }

    public function isPublished(TilesetVersion $version): bool
    {
        return $this->publishedVersion?->equals($version) ?? false;
    }

    public function get(TilesetVersion $version, ParcelId $id): Parcel
    {
        if ($this->unreadable) {
            throw ParcelStoreUnreadable::because('the fake store is unreadable');
        }
        if ($this->publishedVersion === null || !$this->publishedVersion->equals($version)) {
            throw ParcelStoreNotPublished::forVersion($version->value);
        }

        return $this->parcels[$id->value] ?? throw ParcelNotFound::withId($id->value);
    }
}
