<?php

declare(strict_types=1);

namespace ParcelMap\Tests\Fake;

use ParcelMap\Domain\Map\Coordinates;
use ParcelMap\Domain\Map\TilesetVersion;
use ParcelMap\Domain\Search\ParcelMatch;
use ParcelMap\Domain\Search\SearchIndexNotPublished;
use ParcelMap\Domain\Search\SearchIndexRepository;
use ParcelMap\Domain\Search\SearchIndexUnreadable;
use ParcelMap\Domain\Search\SearchQuery;

final class SearchIndexRepositoryUsingMemory implements SearchIndexRepository
{
    /** @var list<array{version: string, query: SearchQuery, near: ?Coordinates, limit: int}> every findParcels() call */
    public array $parcelCalls = [];

    /**
     * @param TilesetVersion|null $publishedVersion the version that has an index; null when there is none
     * @param list<ParcelMatch> $parcels
     */
    public function __construct(
        private readonly ?TilesetVersion $publishedVersion,
        private readonly array $parcels = [],
        private readonly bool $unreadable = false,
    ) {}

    public function isPublished(TilesetVersion $version): bool
    {
        return $this->publishedVersion?->equals($version) ?? false;
    }

    public function findParcels(TilesetVersion $version, SearchQuery $query, ?Coordinates $near, int $limit): array
    {
        $this->parcelCalls[] = ['version' => $version->value, 'query' => $query, 'near' => $near, 'limit' => $limit];
        $this->requireReadable($version);

        $number = $query->parcelNumber();
        if ($number === null) {
            return [];
        }

        $matches = array_values(array_filter(
            $this->parcels,
            static fn(ParcelMatch $match): bool => $number->matches($match->number),
        ));

        return array_slice($matches, 0, $limit);
    }

    private function requireReadable(TilesetVersion $version): void
    {
        if ($this->unreadable) {
            throw SearchIndexUnreadable::because('the fake search index is unreadable');
        }
        if (!$this->isPublished($version)) {
            throw SearchIndexNotPublished::forVersion($version->value);
        }
    }
}
