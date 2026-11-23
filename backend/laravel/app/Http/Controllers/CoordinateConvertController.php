<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertCoordinateRequest;
use App\Services\GeoMathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CoordinateConvertController extends Controller
{
    public function __invoke(ConvertCoordinateRequest $request, GeoMathService $geo): JsonResponse
    {
        $input = $request->validated();
        $field = isset($input['zone']) ? 'zone' : 'lng';

        try {
            if (isset($input['zone'])) {
                ['lat' => $lat, 'lng' => $lng] = $geo->fromUtm(
                    (int) $input['zone'], $input['hemisphere'], $input['easting'], $input['northing'],
                );
            } else {
                $lat = number_format((float) $input['lat'], 8, '.', '');
                $lng = number_format((float) $input['lng'], 8, '.', '');
            }

            $utm = $geo->toUtm($lat, $lng);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => 'Zona UTM di luar jangkauan (hanya zona 46 sampai 54).']);
        }

        return response()->json(['data' => [
            'lat'  => $lat,
            'lng'  => $lng,
            'dms'  => ['lat' => $geo->toDms($lat, true), 'lng' => $geo->toDms($lng, false)],
            'utm'  => $utm,
            'mgrs' => (float) $lat >= -80 && (float) $lat < 84 ? $geo->toMgrs($lat, $lng) : null,
        ]]);
    }
}
