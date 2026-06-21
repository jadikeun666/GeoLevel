<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNetworkLegRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization dicek di controller via $this->authorize('update', $project)
    }

    public function rules(): array
    {
        return [
            'from_point'        => ['required', 'string', 'max:50'],
            'to_point'          => ['required', 'string', 'max:50', 'different:from_point'],
            'observed_delta_h'  => ['required', 'numeric', 'between:-9999.999999,9999.999999'],
            'distance_m'        => ['required', 'numeric', 'min:0.001', 'max:99999.999'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_point.different'    => 'Titik tujuan harus berbeda dari titik asal.',
            'distance_m.min'        => 'Jarak harus lebih besar dari 0.',
            'observed_delta_h.between' => 'Beda tinggi di luar rentang yang wajar.',
        ];
    }
}