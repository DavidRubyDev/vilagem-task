<?php

declare(strict_types=1);

namespace ParcelMap\Infrastructure\Import;

use ParcelMap\Application\Import\BuiltTileset;
use ParcelMap\Application\Import\ConvertedParcels;
use ParcelMap\Application\Import\TileBuildFailed;
use ParcelMap\Application\Import\TilesetBuilder;
use ParcelMap\Domain\Map\TileSizeBudget;
use ParcelMap\Infrastructure\Process\ProcessNotStarted;
use ParcelMap\Infrastructure\Process\ProcessRunner;

/**
 * Builds the vector tileset with tippecanoe. The options keep the map smooth and honest:
 * - a tile never exceeds the budget (--maximum-tile-bytes); when one would, the smallest parcels are left out
 *   (--drop-smallest-as-needed), which in practice only happens below zoom 14. Parcels are never merged, so every
 *   drawn parcel keeps its own land type and, from zoom 14, its own outline;
 * - shared borders are detected so simplification does not open gaps between neighbours;
 * - the parcel id becomes the feature id (--use-attribute-for-id) for the later click-to-detail task;
 * - only landType is kept as a property.
 */
final readonly class TilesetBuilderUsingTippecanoe implements TilesetBuilder
{
    public function __construct(
        private ProcessRunner $runner,
        private string $workDir,
    ) {}

    public function build(array $parcels, TileSizeBudget $budget): BuiltTileset
    {
        $temporaryDirectory = $this->workDir . '/tmp';
        if (!is_dir($temporaryDirectory) && !@mkdir($temporaryDirectory, 0o777, true) && !is_dir($temporaryDirectory)) {
            throw TileBuildFailed::because('cannot create ' . $temporaryDirectory . '.');
        }
        $output = $this->workDir . '/parcels.mbtiles';

        try {
            $result = $this->runner->run([
                'tippecanoe',
                '--output=' . $output,
                '--force',
                '--quiet',
                '--layer=parcels',
                '--name=Parcels',
                '--minimum-zoom=9',
                '--maximum-zoom=16',
                '--use-attribute-for-id=id',
                '--include=landType',
                '--detect-shared-borders',
                '--maximum-tile-bytes=' . $budget->maxBytes,
                '--drop-smallest-as-needed',
                '--simplification=4',
                '--simplify-only-low-zooms',
                '--extend-zooms-if-still-dropping',
                '--no-tiny-polygon-reduction-at-maximum-zoom',
                '--read-parallel',
                '--temporary-directory=' . $temporaryDirectory,
                ...array_map(static fn(ConvertedParcels $converted): string => $converted->path, $parcels),
            ]);
        } catch (ProcessNotStarted $exception) {
            throw TileBuildFailed::because($exception->getMessage());
        }
        if ($result->exitCode !== 0) {
            throw TileBuildFailed::because('tippecanoe failed: ' . trim($result->stderr));
        }

        return new BuiltTileset($output);
    }
}
