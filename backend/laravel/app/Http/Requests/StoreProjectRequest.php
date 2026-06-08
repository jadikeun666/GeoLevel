<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership dijamin di controller via Auth middleware
    }

    /**
     * Inject default dari config sebelum validasi berjalan.
     * Ini memungkinkan frontend tidak mengirim tolerance_class/adjustment_method
     * (field optional di UI), tapi DB tetap selalu terisi — kolom tidak nullable.
     *
     * Engineering Rule: "All engineering constants must live in config/geolevel.php.
     * Never hardcode." (engineering-rules.md)
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'tolerance_class'   => config('geolevel.default_tolerance_class'),
            'adjustment_method' => config('geolevel.default_adjustment_method'),
        ]);
    }

    public function rules(): array
    {
        $classes = implode(',', array_keys(config('geolevel.tolerance_classes')));
        $methods = implode(',', config('geolevel.adjustment_methods'));

        return [
            'name'                => ['required', 'string', 'max:255'],
            'location'            => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'survey_date'         => ['required', 'date'],
            'benchmark_name'      => ['required', 'string', 'max:100'],

            // NUMERIC(12,4) di DB — validasi decimal untuk presisi
            'benchmark_elevation' => ['required', 'numeric', 'between:-9999999.9999,9999999.9999'],

            // Setelah prepareForValidation, field ini selalu ada — jadi required aman
            'tolerance_class'     => ['required', "in:{$classes}"],
            'adjustment_method'   => ['required', "in:{$methods}"],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                => 'Nama proyek wajib diisi.',
            'location.required'            => 'Lokasi proyek wajib diisi.',
            'survey_date.required'         => 'Tanggal survei wajib diisi.',
            'survey_date.date'             => 'Format tanggal tidak valid.',
            'benchmark_name.required'      => 'Nama benchmark (BM) wajib diisi.',
            'benchmark_elevation.required' => 'Elevasi benchmark wajib diisi.',
            'benchmark_elevation.numeric'  => 'Elevasi benchmark harus berupa angka.',
            'tolerance_class.in'           => 'Kelas toleransi tidak valid. Pilihan: LAA, LA, LB, LC.',
            'adjustment_method.in'         => 'Metode perataan tidak valid. Pilihan: equal, bowditch, least_squares.',
        ];
    }
}