<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'point_name'   => 'sometimes|string|max:50',
            'reading_type' => 'sometimes|in:BS,IS,FS',
            'ba'           => 'sometimes|numeric|min:0',
            'bt'           => 'sometimes|numeric|min:0',
            'bb'           => 'sometimes|numeric|min:0',
            'distance_m'   => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ambil nilai dari request atau dari reading yang ada
            $reading = $this->route('reading');
            $ba      = (float) $this->input('ba', $reading->ba);
            $bt      = (float) $this->input('bt', $reading->bt);
            $bb      = (float) $this->input('bb', $reading->bb);
            $limit   = config('geolevel.bt_deviation_limit');

            $btComputed = ($ba + $bb) / 2;
            $deviation  = abs($bt - $btComputed);

            if ($deviation > $limit) {
                $validator->errors()->add(
                    'bt',
                    "BT deviation ({$deviation}) melebihi batas {$limit} m."
                );
            }

            if ($ba < $bt || $bt < $bb) {
                $validator->errors()->add('bt', 'Urutan bacaan harus BA ≥ BT ≥ BB.');
            }
        });
    }
}
