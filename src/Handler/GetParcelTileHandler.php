<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use ParcelMap\Application\Map\GetParcelTileUseCase;
use ParcelMap\Application\Map\ParcelTileInput;
use ParcelMap\Application\Map\ParcelTileNotFound;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

final readonly class GetParcelTileHandler implements RequestHandler
{
    private const string IMMUTABLE = 'public, max-age=31536000, immutable';
    private const int MAX_DIGITS = 9;

    public function __construct(private GetParcelTileUseCase $getParcelTile) {}

    /**
     * @throws StoredTileCorrupt when the stored tile cannot be decompressed for a client without gzip
     */
    public function handle(Request $request): Response
    {
        $parameters = $request->pathParameters;
        $coordinates = [$parameters['z'] ?? '', $parameters['x'] ?? '', $parameters['y'] ?? ''];
        foreach ($coordinates as $value) {
            if (!ctype_digit($value) || strlen($value) > self::MAX_DIGITS) {
                return Response::notFound();
            }
        }

        try {
            $tile = $this->getParcelTile->execute(new ParcelTileInput(
                $parameters['version'] ?? '',
                (int) $coordinates[0],
                (int) $coordinates[1],
                (int) $coordinates[2],
            ));
        } catch (ParcelTileNotFound) {
            return Response::notFound();
        }

        // A tile is immutable for its version, and an empty tile is cached too so the browser never asks twice.
        if ($tile->empty) {
            return Response::noContent(['Cache-Control' => self::IMMUTABLE]);
        }

        $headers = [
            'Content-Type' => 'application/vnd.mapbox-vector-tile',
            'Cache-Control' => self::IMMUTABLE,
            'Vary' => 'Accept-Encoding',
        ];
        if (str_contains(strtolower($request->header('Accept-Encoding')), 'gzip')) {
            return new Response(200, $headers + ['Content-Encoding' => 'gzip'], $tile->gzippedData);
        }

        // MapLibre does not decompress tiles itself, so a client without gzip gets plain bytes.
        // PHP raises a warning before returning false; the failure is reported as an exception instead.
        $plain = @gzdecode($tile->gzippedData);
        if ($plain === false) {
            throw StoredTileCorrupt::cannotDecompress();
        }

        return new Response(200, $headers, $plain);
    }
}
