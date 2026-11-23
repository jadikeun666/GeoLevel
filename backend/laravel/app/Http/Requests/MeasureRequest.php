<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = $this->input('mode') === 'area' ? 3 : 2;
        $max = (int) config('geolevel.outdoor.route_max_vertices');

        return [
            'mode'       => ['required', 'in:line,area'],
            'points'     => ['required', 'array', "min:{$min}", "max:{$max}"],
            'points.*'   => ['required', 'array', 'size:2'],
            'points.*.0' => ['required', 'numeric', 'between:-90,90'],
            'points.*.1' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
