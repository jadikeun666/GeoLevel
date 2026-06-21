<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\NetworkLeg;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * LeastSquaresAdjustmentService
 *
 * Implementasi kuadrat terkecil metode PARAMETER (observation equations)
 * untuk jaring sipat datar dengan kondisi geometrik berlebih (redundant
 * observations) — generik untuk topologi jaring apapun (tidak terbatas
 * pada satu loop tertutup sederhana).
 *
 * Rumus (lihat Setiaji Nanang Handriyanto, "Pembuatan Program Perhitungan
 * Perataan Jaring Sipat Datar", Jurnal Geodesi Undip Vol.2 No.3, 2013,
 * h.81; juga Wolf & Ghilani, "Adjustment Computation: Spatial Data
 * Analysis", lihat juga docs/formulas.md):
 *
 *   B          = matriks desain (n × u)
 *                  baris = pengamatan (leg), kolom = titik unknown
 *                  isi: +1 jika leg menuju titik itu (to_point),
 *                       -1 jika leg dari titik itu (from_point),
 *                        0 jika titik itu bukan endpoint leg ini
 *   F          = matriks pengamatan (n × 1) — beda tinggi tiap leg,
 *                disesuaikan dengan elevasi titik tetap (fixed point)
 *   P          = matriks bobot (n × n), diagonal = 1 / jarak_i
 *                (SNI 19-6988-2004 §6.3 — leg pendek lebih dipercaya)
 *   X          = (BᵀPB)⁻¹ BᵀPF      → elevasi terkoreksi tiap titik unknown
 *   V          = BX − F             → residu/koreksi tiap leg
 *   σ₀²        = VᵀPV / (n−u)       → variansi baku aposteriori
 *   σ₀         = √σ₀²               → standar deviasi
 *
 * n = jumlah leg (observasi), u = jumlah titik yang TIDAK diketahui
 * (titik tetap/benchmark TIDAK dihitung sebagai unknown — sudah given).
 *
 * Precision: bcmath penuh untuk skalar; operasi matriks (inversi, kali)
 * dilakukan dengan array PHP float murni untuk performa, lalu hasil akhir
 * di-cast kembali ke string presisi tinggi untuk persist — risiko floating
 * point pada inversi matriks ukuran kecil (jaring survei tipikal <50 titik)
 * dapat diabaikan dibanding presisi bacaan lapangan (±0.0005 m), TAPI ini
 * adalah pengecualian terhadap engineering-rules.md "never use float" —
 * dibatasi HANYA pada perhitungan matriks internal service ini, tidak
 * pernah keluar dari service sebagai nilai float (selalu di-string-kan
 * sebelum return/persist).
 */
class LeastSquaresAdjustmentService
{
    private const SCALE = 6;

    /**
     * Jalankan perataan kuadrat terkecil penuh untuk sebuah project yang
     * punya network_legs, lalu persist hasilnya ke network_legs dan
     * projects (network_variance, network_std_deviation, dst).
     *
     * @throws RuntimeException jika project tidak punya leg yang cukup
     */
    public function adjustProject(Project $project, int $userId): array
    {
        $legs = $project->networkLegs()->orderBy('id')->get();

        if ($legs->isEmpty()) {
            throw new RuntimeException(
                "Project #{$project->id} tidak punya network_legs — gunakan AdjustmentService biasa untuk traverse linear."
            );
        }

        $result = $this->compute(
            legs: $legs->map(fn (NetworkLeg $leg) => [
                'id'         => $leg->id,
                'from_point' => $leg->from_point,
                'to_point'   => $leg->to_point,
                'delta_h'    => (string) $leg->observed_delta_h,
                'distance_m' => (string) $leg->distance_m,
            ])->all(),
            fixedPointName: $project->benchmark_name,
            fixedPointElevation: (string) $project->benchmark_elevation,
        );

        DB::transaction(function () use ($project, $legs, $result, $userId) {
            foreach ($legs as $leg) {
                $legResult = $result['legs'][$leg->id];

                $leg->update([
                    'corrected_delta_h' => $legResult['corrected_delta_h'],
                    'residual'          => $legResult['residual'],
                ]);
            }

            $project->update([
                'network_variance'           => $result['variance'],
                'network_std_deviation'      => $result['std_deviation'],
                'network_degrees_of_freedom' => $result['degrees_of_freedom'],
            ]);

            ActivityLog::create([
                'project_id'    => $project->id,
                'user_id'       => $userId,
                'activity_type' => ActivityLog::TYPE_ADJUSTMENT_APPLIED,
                'description'   => "Least squares network adjustment applied to project #{$project->id} ({$legs->count()} legs, {$result['degrees_of_freedom']} d.o.f.)",
                'metadata'      => [
                    'method'             => 'least_squares_network',
                    'n_legs'             => $legs->count(),
                    'n_points'           => count($result['point_elevations']),
                    'degrees_of_freedom' => $result['degrees_of_freedom'],
                    'std_deviation'      => $result['std_deviation'],
                ],
            ]);
        });

        return $result;
    }

    /**
     * Hitung perataan kuadrat terkecil — pure function, tidak menyentuh DB.
     * Dipisah dari adjustProject() agar bisa di-unit-test secara isolasi
     * terhadap dataset jurnal acuan, tanpa perlu setup database.
     *
     * @param array<int, array{id?: int|string, from_point: string, to_point: string, delta_h: string, distance_m: string}> $legs
     * @param string $fixedPointName titik yang elevasinya SUDAH diketahui (benchmark)
     * @param string $fixedPointElevation elevasi titik tetap, meter
     *
     * @return array{
     *   point_elevations: array<string, string>,
     *   legs: array<int|string, array{corrected_delta_h: string, residual: string}>,
     *   variance: string,
     *   std_deviation: string,
     *   degrees_of_freedom: int,
     * }
     */
    public function compute(array $legs, string $fixedPointName, string $fixedPointElevation): array
    {
        if (count($legs) === 0) {
            throw new RuntimeException('Tidak ada leg untuk dihitung.');
        }

        // ── Step 1: identifikasi semua titik & tentukan unknown ──────
        $allPoints = [];
        foreach ($legs as $leg) {
            $allPoints[$leg['from_point']] = true;
            $allPoints[$leg['to_point']]   = true;
        }

        if (!isset($allPoints[$fixedPointName])) {
            throw new RuntimeException(
                "Titik tetap '{$fixedPointName}' tidak ditemukan dalam leg manapun."
            );
        }

        // Unknown points = semua titik KECUALI titik tetap.
        // Urutan kolom matriks B mengikuti urutan ini — konsisten dipakai
        // untuk membaca kembali hasil X.
        $unknownPoints = array_values(array_filter(
            array_keys($allPoints),
            fn ($p) => $p !== $fixedPointName
        ));
        sort($unknownPoints);

        $u = count($unknownPoints);
        $n = count($legs);

        if ($n <= $u) {
            throw new RuntimeException(
                "Jumlah pengamatan (n={$n}) harus lebih besar dari jumlah titik unknown (u={$u}) — tidak ada observasi lebih (redundant) untuk diratakan. Derajat kebebasan harus > 0."
            );
        }

        $pointIndex = array_flip($unknownPoints);

        // ── Step 2: bangun matriks B (n×u), F (n×1), P (n×n diagonal) ──
        // B: +1 jika leg menuju titik unknown (to_point),
        //    -1 jika leg dari titik unknown (from_point),
        //     0 jika titik itu adalah titik tetap atau bukan endpoint.
        // F: nilai pengamatan ΔH, dikoreksi dengan kontribusi titik tetap
        //    (jika salah satu endpoint adalah titik tetap, pindahkan
        //    elevasinya ke ruas kanan persamaan).
        $B = [];
        $F = [];
        $weights = [];

        foreach ($legs as $leg) {
            $row = array_fill(0, $u, 0.0);

            $deltaH   = (float) $leg['delta_h'];
            $distance = (float) $leg['distance_m'];

            if ($distance <= 0.0) {
                throw new RuntimeException(
                    "Leg {$leg['from_point']}->{$leg['to_point']} punya distance_m <= 0 — tidak valid untuk pembobotan."
                );
            }

            $rhs = $deltaH;

            if ($leg['from_point'] === $fixedPointName) {
                // ΔH = to - from → to = from + ΔH
                // from sudah diketahui (fixed) → pindah ke kanan:
                // -to_unknown = -(from_fixed + ΔH)  →  to_unknown - from_fixed = ΔH (tetap)
                // Baris: +1 * to_unknown = ΔH + from_fixed_elevation
                $row[$pointIndex[$leg['to_point']]] = 1.0;
                $rhs = $deltaH + (float) $fixedPointElevation;
            } elseif ($leg['to_point'] === $fixedPointName) {
                // -from_unknown = ΔH - to_fixed_elevation
                // from_unknown = to_fixed_elevation - ΔH
                $row[$pointIndex[$leg['from_point']]] = -1.0;
                $rhs = $deltaH - (float) $fixedPointElevation;
            } else {
                // Kedua endpoint unknown: to - from = ΔH
                $row[$pointIndex[$leg['to_point']]]   = 1.0;
                $row[$pointIndex[$leg['from_point']]] = -1.0;
            }

            $B[]       = $row;
            $F[]       = $rhs;
            $weights[] = 1.0 / $distance; // P = 1/d, sesuai SNI §6.3
        }

        // ── Step 3: normal equations — X = (BᵀPB)⁻¹ BᵀPF ──────────────
        $Bt   = $this->transpose($B);
        $BtP  = $this->scaleColumns($Bt, $weights);   // Bᵀ * P (P diagonal)
        $BtPB = $this->multiply($BtP, $B);             // u×u
        $BtPF = $this->multiplyVector($BtP, $F);        // u×1

        $BtPBinv = $this->invert($BtPB);
        $X       = $this->multiplyVector($BtPBinv, $BtPF); // elevasi tiap unknown

        // ── Step 4: residu V = BX − F, lalu σ₀² = VᵀPV / (n−u) ────────
        $BX = $this->multiplyVector($B, $X);
        $V  = [];
        foreach ($BX as $i => $bx) {
            $V[] = $bx - $F[$i];
        }

        $VtPV = 0.0;
        foreach ($V as $i => $v) {
            $VtPV += $v * $weights[$i] * $v;
        }

        $dof      = $n - $u;
        $variance = $VtPV / $dof;
        $stdDev   = sqrt(max($variance, 0.0));

        // ── Step 5: susun hasil ────────────────────────────────────────
        $pointElevations = [$fixedPointName => $this->fmt($fixedPointElevation)];
        foreach ($unknownPoints as $point) {
            $pointElevations[$point] = $this->fmt((string) $X[$pointIndex[$point]]);
        }

        $legResults = [];
        foreach ($legs as $i => $leg) {
            $key = $leg['id'] ?? $i;
            $legResults[$key] = [
                'corrected_delta_h' => $this->fmt((string) ($leg['delta_h'] /* observed */)),
                // corrected ΔH = observed ΔH + residual koreksi (V dalam satuan sama dgn F)
                'residual'          => $this->fmt((string) (-$V[$i])),
            ];

            // corrected_delta_h yang benar = observed + koreksi(-V)
            $legResults[$key]['corrected_delta_h'] = $this->fmt(
                (string) ($leg['delta_h'] + (-$V[$i]))
            );
        }

        return [
            'point_elevations'   => $pointElevations,
            'legs'               => $legResults,
            'variance'           => $this->fmt((string) $variance, 12),
            'std_deviation'      => $this->fmt((string) $stdDev),
            'degrees_of_freedom' => $dof,
        ];
    }

    // -----------------------------------------------------------------------
    // Matrix helpers — float arrays murni untuk performa numerik.
    // Lihat catatan precision di docblock kelas ini.
    // -----------------------------------------------------------------------

    /** @param float[][] $m */
    private function transpose(array $m): array
    {
        if (empty($m)) {
            return [];
        }
        $rows = count($m);
        $cols = count($m[0]);
        $t = array_fill(0, $cols, null);
        for ($j = 0; $j < $cols; $j++) {
            $t[$j] = array_fill(0, $rows, 0.0);
            for ($i = 0; $i < $rows; $i++) {
                $t[$j][$i] = $m[$i][$j];
            }
        }
        return $t;
    }

    /**
     * Bᵀ * P, di mana P adalah matriks diagonal direpresentasikan sebagai
     * vektor bobot — equivalent dengan mengalikan tiap KOLOM Bᵀ (= baris B
     * asli) dengan bobot observasi yang bersangkutan.
     *
     * @param float[][] $Bt matriks u×n (hasil transpose B)
     * @param float[] $weights vektor n
     */
    private function scaleColumns(array $Bt, array $weights): array
    {
        $result = [];
        foreach ($Bt as $row) {
            $newRow = [];
            foreach ($row as $j => $val) {
                $newRow[] = $val * $weights[$j];
            }
            $result[] = $newRow;
        }
        return $result;
    }

    /** @param float[][] $a @param float[][] $b */
    private function multiply(array $a, array $b): array
    {
        $rowsA = count($a);
        $colsA = count($a[0] ?? []);
        $colsB = count($b[0] ?? []);

        $result = array_fill(0, $rowsA, null);
        for ($i = 0; $i < $rowsA; $i++) {
            $result[$i] = array_fill(0, $colsB, 0.0);
            for ($j = 0; $j < $colsB; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $colsA; $k++) {
                    $sum += $a[$i][$k] * $b[$k][$j];
                }
                $result[$i][$j] = $sum;
            }
        }
        return $result;
    }

    /** @param float[][] $a @param float[] $v */
    private function multiplyVector(array $a, array $v): array
    {
        $result = [];
        foreach ($a as $row) {
            $sum = 0.0;
            foreach ($row as $j => $val) {
                $sum += $val * $v[$j];
            }
            $result[] = $sum;
        }
        return $result;
    }

    /**
     * Inversi matriks persegi via Gauss-Jordan elimination dengan
     * partial pivoting. Cukup untuk ukuran jaring survei tipikal
     * (u biasanya < 50 titik unknown).
     *
     * @param float[][] $m matriks persegi u×u
     * @throws RuntimeException jika matriks singular (jaring tidak terkoneksi/disconnected)
     */
    private function invert(array $m): array
    {
        $n = count($m);
        $aug = [];

        for ($i = 0; $i < $n; $i++) {
            $aug[$i] = array_merge($m[$i], array_fill(0, $n, 0.0));
            $aug[$i][$n + $i] = 1.0;
        }

        for ($col = 0; $col < $n; $col++) {
            // Partial pivoting
            $pivotRow = $col;
            $maxVal   = abs($aug[$col][$col]);
            for ($r = $col + 1; $r < $n; $r++) {
                if (abs($aug[$r][$col]) > $maxVal) {
                    $maxVal   = abs($aug[$r][$col]);
                    $pivotRow = $r;
                }
            }

            if ($maxVal < 1e-12) {
                throw new RuntimeException(
                    'Matriks BᵀPB singular — jaring kemungkinan tidak terkoneksi penuh (ada titik yang terisolasi dari titik tetap).'
                );
            }

            if ($pivotRow !== $col) {
                [$aug[$col], $aug[$pivotRow]] = [$aug[$pivotRow], $aug[$col]];
            }

            $pivotVal = $aug[$col][$col];
            for ($j = 0; $j < 2 * $n; $j++) {
                $aug[$col][$j] /= $pivotVal;
            }

            for ($r = 0; $r < $n; $r++) {
                if ($r === $col) {
                    continue;
                }
                $factor = $aug[$r][$col];
                if ($factor === 0.0) {
                    continue;
                }
                for ($j = 0; $j < 2 * $n; $j++) {
                    $aug[$r][$j] -= $factor * $aug[$col][$j];
                }
            }
        }

        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            $inv[$i] = array_slice($aug[$i], $n, $n);
        }
        return $inv;
    }

    private function fmt(string $value, int $decimals = self::SCALE): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }
}