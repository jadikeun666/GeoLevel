<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sequence_no'  => 'required|integer|min:1',
            'point_name'   => 'required|string|max:50',
            'reading_type' => 'required|in:BS,IS,FS',
            'ba'           => 'required|numeric|min:0',
            'bt'           => 'required|numeric|min:0',
            'bb'           => 'required|numeric|min:0',
            'distance_m'   => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ba    = (float) $this->input('ba');
            $bt    = (float) $this->input('bt');
            $bb    = (float) $this->input('bb');
            $limit = config('geolevel.bt_deviation_limit');

            $btComputed  = ($ba + $bb) / 2;
            $deviation   = abs($bt - $btComputed);

            if ($deviation > $limit) {
                $validator->errors()->add(
                    'bt',
                    "BT deviation ({$deviation}) melebihi batas {$limit} m. " .
                    "BT harus mendekati (BA+BB)/2 = {$btComputed}"
                );
            }

            // BA harus >= BT >= BB
            if ($ba < $bt || $bt < $bb) {
                $validator->errors()->add('bt', 'Urutan bacaan harus BA ≥ BT ≥ BB.');
            }
        });
    }
}
