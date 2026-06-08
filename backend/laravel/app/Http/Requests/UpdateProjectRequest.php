<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership dijamin di controller via $this->authorize('update', $project)
    }

    public function rules(): array
    {
        $classes = implode(',', array_keys(config('geolevel.tolerance_classes')));
        $methods = implode(',', config('geolevel.adjustment_methods'));

        return [
            // 'sometimes|required' = field boleh tidak dikirim,
            // tapi jika dikirim maka wajib diisi (tidak boleh kosong)
            'name'                => ['sometimes', 'required', 'string', 'max:255'],
            'location'            => ['sometimes', 'required', 'string', 'max:255'],
            'survey_date'         => ['sometimes', 'required', 'date'],
            'benchmark_name'      => ['sometimes', 'required', 'string', 'max:100'],
            'benchmark_elevation' => ['sometimes', 'required', 'numeric', 'between:-9999999.9999,9999999.9999'],
            'description'         => ['nullable', 'string'],

            // Nilai dari config — tidak hardcode (engineering-rules.md)
            'tolerance_class'     => ['sometimes', 'required', "in:{$classes}"],
            'adjustment_method'   => ['sometimes', 'required', "in:{$methods}"],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                => 'Nama proyek tidak boleh dikosongkan.',
            'location.required'            => 'Lokasi proyek tidak boleh dikosongkan.',
            'survey_date.date'             => 'Format tanggal tidak valid.',
            'benchmark_name.required'      => 'Nama benchmark (BM) tidak boleh dikosongkan.',
            'benchmark_elevation.numeric'  => 'Elevasi benchmark harus berupa angka.',
            'tolerance_class.in'           => 'Kelas toleransi tidak valid. Pilihan: LAA, LA, LB, LC.',
            'adjustment_method.in'         => 'Metode perataan tidak valid. Pilihan: equal, bowditch, least_squares.',
        ];
    }
}