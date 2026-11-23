<?php

declare(strict_types=1);

namespace ParcelMap\Handler;

use ParcelMap\Application\Parcel\GetParcelDetailUseCase;
use ParcelMap\Application\Parcel\ParcelDetailInput;
use ParcelMap\Application\Parcel\ParcelDetailNotFound;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\RequestHandler;
use ParcelMap\Handler\Http\Response;

final readonly class GetParcelDetailHandler implements RequestHandler
{
    private const string IMMUTABLE = 'public, max-age=31536000, immutable';
    // A parcel id is exact in JSON only up to 2^53 - 1, which has 16 digits.
    private const int MAX_DIGITS = 15;

    public function __construct(private GetParcelDetailUseCase $getParcelDetail) {}

    public function handle(Request $request): Response
    {
        $parameters = $request->pathParameters;
        $id = $parameters['id'] ?? '';
        if (!ctype_digit($id) || strlen($id) > self::MAX_DIGITS) {
            return Response::notFound();
        }

        try {
            $parcel = $this->getParcelDetail->execute(new ParcelDetailInput($parameters['version'] ?? '', (int) $id));
        } catch (ParcelDetailNotFound) {
            return Response::notFound();
        }

        // The details are immutable for their tileset version.
        return Response::json([
            'id' => $parcel->id,
            'parcelNumber' => $parcel->parcelNumber,
            'kind' => $parcel->kind,
            'cadastralArea' => ['code' => $parcel->cadastralAreaCode, 'name' => $parcel->cadastralAreaName],
            'municipality' => ['code' => $parcel->municipalityCode, 'name' => $parcel->municipalityName],
            'landTypeCode' => $parcel->landTypeCode,
            'landUseCode' => $parcel->landUseCode,
            'areaSquareMetres' => $parcel->areaSquareMetres,
            'boundary' => ['type' => 'Polygon', 'coordinates' => $parcel->boundaryRings],
            'cadastreViewerUrl' => $parcel->cadastreViewerUrl,
            'bounds' => ['west' => $parcel->west, 'south' => $parcel->south, 'east' => $parcel->east, 'north' => $parcel->north],
        ])->withHeader('Cache-Control', self::IMMUTABLE);
    }
}
