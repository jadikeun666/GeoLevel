<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership dijamin di controller
    }

    public function rules(): array
    {
        return [
            // sequence_no TIDAK boleh diubah setelah tersimpan — immutable
            // reading_type boleh diubah (misal salah input BS/IS/FS)
            'point_name'   => ['sometimes', 'required', 'string', 'max:50'],
            'reading_type' => ['sometimes', 'required', 'in:BS,IS,FS'],

            // BA, BT, BB: NUMERIC(10,4) — positif, max 4 desimal
            'ba'           => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999.9999'],
            'bt'           => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999.9999'],
            'bb'           => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999.9999'],

            // NULL berarti pakai distance_computed (generated column di DB)
            'distance_m'   => ['nullable', 'numeric', 'min:0', 'max:9999.999'],
            'notes'        => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validasi BT deviation setelah rules() lolos.
     *
     * Business Rule #4: "Validate BT deviation ≤ 0.002 m — reject reading if violated"
     * Precision Policy: "Never use PHP float arithmetic — use bcmath"
     *
     * Untuk update partial (PATCH), ambil nilai dari request jika ada,
     * fallback ke nilai existing di DB dari model reading.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Jangan lanjut jika field dasar sudah error
            if ($validator->errors()->hasAny(['ba', 'bt', 'bb'])) {
                return;
            }

            /** @var \App\Models\Reading $reading */
            $reading = $this->route('reading');

            // Ambil dari request jika dikirim, fallback ke nilai existing di DB
            // Cast ke string untuk bcmath — jangan pakai (float)
            $ba = (string) $this->input('ba', $reading->ba);
            $bt = (string) $this->input('bt', $reading->bt);
            $bb = (string) $this->input('bb', $reading->bb);

            $scale = 6; // presisi internal bcmath

            // BT_computed = (BA + BB) / 2  — formula dari formulas.md
            $btComputed = bcdiv(bcadd($ba, $bb, $scale), '2', $scale);

            // deviation = |BT_field − BT_computed|
            $deviation = bcsub($bt, $btComputed, $scale);
            if (bccomp($deviation, '0', $scale) < 0) {
                $deviation = bcsub('0', $deviation, $scale); // abs()
            }

            $limit = (string) config('geolevel.bt_deviation_limit');

            // Business Rule #4: reject jika deviation > limit
            if (bccomp($deviation, $limit, $scale) > 0) {
                $validator->errors()->add(
                    'bt',
                    "Deviasi BT ({$deviation} m) melebihi batas {$limit} m. " .
                    "BT lapangan harus mendekati (BA+BB)/2 = {$btComputed} m."
                );
            }

            // Konsistensi urutan: BA ≥ BT ≥ BB
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
            'point_name.required'   => 'Nama titik/patok tidak boleh dikosongkan.',
            'reading_type.in'       => 'Tipe bacaan tidak valid. Pilihan: BS, IS, FS.',
            'ba.numeric'            => 'Bacaan Atas (BA) harus berupa angka.',
            'bt.numeric'            => 'Bacaan Tengah (BT) harus berupa angka.',
            'bb.numeric'            => 'Bacaan Bawah (BB) harus berupa angka.',
            'distance_m.numeric'    => 'Jarak optis harus berupa angka.',
            'distance_m.min'        => 'Jarak optis tidak boleh negatif.',
        ];
    }
}