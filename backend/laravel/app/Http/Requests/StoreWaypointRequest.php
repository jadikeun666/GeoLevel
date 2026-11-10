<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaypointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name'            => ['required', 'string', 'max:100'],
            'lat'             => ['required', 'numeric', 'between:-90,90'],
            'lng'             => ['required', 'numeric', 'between:-180,180'],
            'elevation_ref'   => ['nullable', 'numeric', 'between:-99999999,99999999'],
            'gps_accuracy_m'  => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'category'        => ['nullable', Rule::in(config('geolevel.outdoor.waypoint_categories'))],
            'source'          => ['nullable', 'in:manual,picker'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'project_id'      => [
                'nullable',
                Rule::exists('projects', 'id')->where('user_id', $userId),
            ],
            'survey_point_id' => [
                'nullable',
                Rule::exists('survey_points', 'id')->where(
                    fn ($q) => $q->whereIn('project_id', Project::where('user_id', $userId)->select('id'))
                ),
            ],
        ];
    }
}
