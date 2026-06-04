<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'name'                => 'required|string|max:255',
            'location'            => 'required|string|max:255',
            'description'         => 'nullable|string',
            'survey_date'         => 'required|date',
            'benchmark_name'      => 'required|string|max:100',
            'benchmark_elevation' => 'required|numeric',
            'tolerance_class'     => "required|in:{$classes}",
            'adjustment_method'   => "required|in:{$methods}",
        ];
    }
}
