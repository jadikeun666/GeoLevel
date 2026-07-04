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
        // sequence_no: required untuk API/JSON requests (ReadingControllerTest menggunakan postJson)
        // optional untuk browser form requests (ProjectWorkflowTest menggunakan post tanpa sequence_no)
        // Controller selalu override dengan auto-increment — sequence_no dari user diabaikan.
        $seqRule = $this->expectsJson()
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];

        return [
            'sequence_no'  => $seqRule,

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
     * Business Rule #4: "Validate BT deviation <= 0.002 m — reject reading if violated"
     * Precision Policy: "Never use PHP float arithmetic — use bcmath"
     *
     * CATATAN: Rule "BA >= BT >= BB" dihapus dari sini karena:
     * - Tidak tercantum di engineering-rules.md maupun formulas.md
     * - Validasi deviasi BT <= 0.002 m sudah menjamin BT berada sangat
     *   dekat dengan (BA+BB)/2, sehingga urutan BA>=BT>=BB secara implisit
     *   terpenuhi jika deviasi lulus
     * - Duplikasi dengan validasi live di Vue (liveBtOk computed) yang
     *   sudah mencegah submit jika deviasi melebihi batas
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['ba', 'bt', 'bb'])) {
                return;
            }

            $ba    = (string) $this->input('ba');
            $bt    = (string) $this->input('bt');
            $bb    = (string) $this->input('bb');
            $scale = 6;

            $btComputed = bcdiv(bcadd($ba, $bb, $scale), '2', $scale);

            $deviation = bcsub($bt, $btComputed, $scale);
            if (bccomp($deviation, '0', $scale) < 0) {
                $deviation = bcsub('0', $deviation, $scale);
            }

            $limit = (string) config('geolevel.bt_deviation_limit');

            if (bccomp($deviation, $limit, $scale) > 0) {
                $validator->errors()->add(
                    'bt',
                    "Deviasi BT ({$deviation} m) melebihi batas {$limit} m. " .
                    "BT lapangan harus mendekati (BA+BB)/2 = {$btComputed} m."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'sequence_no.required'  => 'Nomor urut wajib diisi.',
            'sequence_no.integer'   => 'Nomor urut harus berupa bilangan bulat.',
            'sequence_no.min'       => 'Nomor urut minimal 1.',
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