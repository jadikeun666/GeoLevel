<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertCoordinateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Either lat+lng, or zone+hemisphere+easting+northing (docs/outdoor.md, 19.1). */
    public function rules(): array
    {
        return [
            'lat'        => ['required_without:zone', 'numeric', 'between:-90,90'],
            'lng'        => ['required_without:zone', 'numeric', 'between:-180,180'],
            'zone'       => ['required_without:lat', 'integer'],
            'hemisphere' => ['required_with:zone', 'in:N,S'],
            'easting'    => ['required_with:zone', 'numeric'],
            'northing'   => ['required_with:zone', 'numeric'],
        ];
    }
}
