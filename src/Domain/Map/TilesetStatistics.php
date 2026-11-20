<?php

declare(strict_types=1);

namespace ParcelMap\Domain\Map;

/** Tile count and largest tile per zoom level of a built tileset. */
final readonly class TilesetStatistics
{
    /** @var non-empty-list<ZoomTileStatistics> ascending by zoom */
    private array $zooms;

    public function __construct(ZoomTileStatistics ...$zooms)
    {
        if ($zooms === []) {
            throw InvalidTilesetStatistics::empty();
        }

        $seen = [];
        foreach ($zooms as $zoom) {
            if (isset($seen[$zoom->zoom])) {
                throw InvalidTilesetStatistics::duplicateZoom($zoom->zoom);
            }
            $seen[$zoom->zoom] = true;
        }

        usort($zooms, static fn(ZoomTileStatistics $a, ZoomTileStatistics $b): int => $a->zoom <=> $b->zoom);
        $this->zooms = $zooms;
    }

    /**
     * @return non-empty-list<ZoomTileStatistics>
     */
    public function zooms(): array
    {
        return $this->zooms;
    }

    public function tileCount(): int
    {
        return array_sum(array_map(static fn(ZoomTileStatistics $zoom): int => $zoom->tileCount, $this->zooms));
    }

    /** The zoom level with the biggest largest tile; the lowest zoom level on a tie. */
    public function largest(): ZoomTileStatistics
    {
        $largest = $this->zooms[0];
        foreach ($this->zooms as $zoom) {
            if ($zoom->largestTileBytes > $largest->largestTileBytes) {
                $largest = $zoom;
            }
        }

        return $largest;
    }

    public function fitsWithin(TileSizeBudget $budget): bool
    {
        return $budget->allows($this->largest()->largestTileBytes);
    }
}
