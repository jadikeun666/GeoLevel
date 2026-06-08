<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership project dijamin di controller via $this->authorize('update', $project)
    }

    public function rules(): array
    {
        return [
            // sequence_no TIDAK di-input user — di-auto-increment di ReadingController::store()
            'point_name'   => ['required', 'string', 'max:50'],
            'reading_type' => ['required', 'in:BS,IS,FS'],

            // BA, BT, BB: NUMERIC(10,4) di DB — nilai positif, maksimal 4 desimal
            'ba'           => ['required', 'numeric', 'min:0', 'max:9999.9999'],
            'bt'           => ['required', 'numeric', 'min:0', 'max:9999.9999'],
            'bb'           => ['required', 'numeric', 'min:0', 'max:9999.9999'],

            // distance_m: override manual, NULL berarti pakai distance_computed dari DB
            'distance_m'   => ['nullable', 'numeric', 'min:0', 'max:9999.999'],
            'notes'        => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validasi engineering setelah rules() lolos.
     *
     * Business Rule #4: "Validate BT deviation ≤ 0.002 m — reject reading if violated"
     * Precision Policy: "Never use PHP float arithmetic — use bcmath"
     *
     * Menggunakan bcmath bukan (float) cast karena floating-point PHP
     * tidak presisi untuk nilai survei 4 desimal.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Jangan lanjut jika field dasar sudah error
            if ($validator->errors()->hasAny(['ba', 'bt', 'bb'])) {
                return;
            }

            $ba    = (string) $this->input('ba');
            $bt    = (string) $this->input('bt');
            $bb    = (string) $this->input('bb');
            $scale = 6; // presisi bcmath untuk kalkulasi internal

            // BT_computed = (BA + BB) / 2  — formula dari formulas.md
            $btComputed = bcdiv(bcadd($ba, $bb, $scale), '2', $scale);

            // deviation = |BT_field − BT_computed|
            $deviation = bcsub($bt, $btComputed, $scale);
            if (bccomp($deviation, '0', $scale) < 0) {
                $deviation = bcsub('0', $deviation, $scale); // abs()
            }

            $limit = (string) config('geolevel.bt_deviation_limit'); // '0.002'

            // Business Rule #4: reject jika deviation > limit
            if (bccomp($deviation, $limit, $scale) > 0) {
                $validator->errors()->add(
                    'bt',
                    "Deviasi BT ({$deviation} m) melebihi batas {$limit} m. " .
                    "BT lapangan harus mendekati (BA+BB)/2 = {$btComputed} m."
                );
            }

            // Konsistensi urutan: BA ≥ BT ≥ BB
            // Jika BA < BT atau BT < BB, bacaan tidak valid secara fisik
            if (bccomp($ba, $bt, $scale) < 0 || bccomp($bt, $bb, $scale) < 0) {
                $validator->errors()->add(
                    'bt',
                    'Urutan bacaan tidak valid. Harus: BA ≥ BT ≥ BB.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'point_name.required'   => 'Nama titik/patok wajib diisi.',
            'point_name.max'        => 'Nama titik maksimal 50 karakter.',
            'reading_type.required' => 'Tipe bacaan wajib dipilih (BS, IS, atau FS).',
            'reading_type.in'       => 'Tipe bacaan tidak valid. Pilihan: BS, IS, FS.',
            'ba.required'           => 'Bacaan Atas (BA) wajib diisi.',
            'ba.numeric'            => 'Bacaan Atas (BA) harus berupa angka.',
            'bt.required'           => 'Bacaan Tengah (BT) wajib diisi.',
            'bt.numeric'            => 'Bacaan Tengah (BT) harus berupa angka.',
            'bb.required'           => 'Bacaan Bawah (BB) wajib diisi.',
            'bb.numeric'            => 'Bacaan Bawah (BB) harus berupa angka.',
            'distance_m.numeric'    => 'Jarak optis harus berupa angka.',
            'distance_m.min'        => 'Jarak optis tidak boleh negatif.',
        ];
    }
}