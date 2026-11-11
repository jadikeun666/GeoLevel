<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportGpxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) floor((float) config('geolevel.outdoor.gpx_max_size_mb') * 1024);

        return [
            'file'       => ['required', 'file', 'extensions:gpx', 'max:' . $maxKb],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'   => 'Pilih file GPX terlebih dahulu.',
            'file.extensions' => 'File harus berekstensi .gpx.',
            'file.max'        => 'Ukuran file GPX melebihi batas :max KB.',
        ];
    }
}
