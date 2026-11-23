<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeasureRequest;
use App\Services\GeoMathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MeasureController extends Controller
{
    public function __invoke(MeasureRequest $request, GeoMathService $geo): JsonResponse
    {
        try {
            $result = $geo->measure(array_values($request->validated('points')), $request->validated('mode') === 'area');
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'points' => 'Titik berada di luar zona UTM 46 sampai 54 atau melintasi lebih dari satu zona.',
            ]);
        }

        return response()->json(['data' => $result]);
    }
}
