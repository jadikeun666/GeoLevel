<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'point_name'     => ['required', 'string', 'max:50'],
            'lat'            => ['required', 'numeric', 'between:-90,90'],
            'lng'            => ['required', 'numeric', 'between:-180,180'],
            'point_type'     => ['required', 'in:BM,TP,IS,CP'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'source'         => ['required', 'in:manual,gpx,picker'],
            'elevation_ref'  => ['nullable', 'numeric'],
            'gps_accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
