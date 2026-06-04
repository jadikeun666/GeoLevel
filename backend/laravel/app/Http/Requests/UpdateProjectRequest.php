<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $methods = implode(',', config('geolevel.adjustment_methods'));
        $classes = implode(',', array_keys(config('geolevel.tolerance_classes')));

        return [
            'name'                => 'sometimes|string|max:255',
            'location'            => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'survey_date'         => 'sometimes|date',
            'benchmark_name'      => 'sometimes|string|max:100',
            'benchmark_elevation' => 'sometimes|numeric',
            'tolerance_class'     => "sometimes|in:{$classes}",
            'adjustment_method'   => "sometimes|in:{$methods}",
        ];
    }
}
