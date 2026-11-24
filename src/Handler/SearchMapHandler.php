<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use ParcelMap\Application\Search\InvalidSearchRequest;
use ParcelMap\Application\Search\ParcelResultOutput;
use ParcelMap\Application\Search\SearchMapInput;
use ParcelMap\Application\Search\SearchMapUseCase;
use ParcelMap\Application\Search\SearchNotAvailable;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

final readonly class SearchMapHandler implements RequestHandler
{
    private const string IMMUTABLE = 'public, max-age=31536000, immutable';
    private const string NEAR_PATTERN = '~^(-?[0-9]{1,3}(?:\.[0-9]{1,8})?),(-?[0-9]{1,2}(?:\.[0-9]{1,8})?)$~';

    public function __construct(private SearchMapUseCase $searchMap) {}

    public function handle(Request $request): Response
    {
        $text = $request->queryParameter('q');
        if ($text === null) {
            return Response::badRequest();
        }
        $near = $request->queryParameter('near');
        $longitude = null;
        $latitude = null;
        if ($near !== null) {
            if (preg_match(self::NEAR_PATTERN, $near, $parts) !== 1) {
                return Response::badRequest();
            }
            $longitude = (float) $parts[1];
            $latitude = (float) $parts[2];
        }

        try {
            $output = $this->searchMap->execute(new SearchMapInput($request->pathParameters['version'] ?? '', $text, $longitude, $latitude));
        } catch (InvalidSearchRequest) {
            return Response::badRequest();
        } catch (SearchNotAvailable) {
            return Response::notFound();
        }

        // The results are immutable for their tileset version.
        return Response::json([
            'places' => $output->places,
            'parcels' => array_map(
                static fn(ParcelResultOutput $parcel): array => [
                    'id' => $parcel->id,
                    'parcelNumber' => $parcel->parcelNumber,
                    'kind' => $parcel->kind,
                    'cadastralArea' => ['code' => $parcel->cadastralAreaCode, 'name' => $parcel->cadastralAreaName],
                    'municipality' => ['code' => $parcel->municipalityCode, 'name' => $parcel->municipalityName],
                ],
                $output->parcels,
            ),
        ])->withHeader('Cache-Control', self::IMMUTABLE);
    }
}
