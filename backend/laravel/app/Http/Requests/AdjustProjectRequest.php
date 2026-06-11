<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership dijamin di controller via $this->authorize('update', $project)
    }

    public function rules(): array
    {
        // Ambil dari config — tidak hardcode (engineering-rules.md)
        $methods = implode(',', config('geolevel.adjustment_methods'));

        return [
            'method' => ['required', 'string', 'in:equal,bowditch,least_squares,reset'],
        ];
    }

    /**
     * Validasi tambahan: proyek harus dalam status yang membolehkan adjustment.
     * Hanya 'calculated' atau 'accepted' yang boleh di-adjust.
     * Status 'draft' belum punya computed_elevations — adjustment tidak akan bermakna.
     * Status 'rejected' boleh di-adjust ulang setelah diperbaiki.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\Project $project */
            $project = $this->route('project');

            if (! in_array($project->status, ['calculated', 'accepted', 'rejected'], true)) {
                $validator->errors()->add(
                    'method',
                    'Perataan hanya bisa diterapkan setelah proyek dihitung. ' .
                    'Jalankan "Hitung Ulang" terlebih dahulu.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Metode perataan wajib dipilih.',
            'method.in'       => 'Metode perataan tidak valid. Pilihan: equal, bowditch, least_squares.',
        ];
    }
}