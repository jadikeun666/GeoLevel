<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('geolevel.outdoor.route_max_vertices');

        return [
            'name'         => ['required', 'string', 'max:150'],
            'geometry'     => ['required', 'array', 'min:2', "max:{$max}"],
            'geometry.*'   => ['required', 'array', 'size:2'],
            'geometry.*.0' => ['required', 'numeric', 'between:-90,90'],
            'geometry.*.1' => ['required', 'numeric', 'between:-180,180'],
            'notes'        => ['nullable', 'string', 'max:500'],
            'project_id'   => [
                'nullable',
                Rule::exists('projects', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
