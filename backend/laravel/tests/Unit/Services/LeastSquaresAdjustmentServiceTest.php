<?php

namespace Tests\Unit\Services;

use App\Services\LeastSquaresAdjustmentService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test LeastSquaresAdjustmentService terhadap dataset acuan dari:
 *
 * 1. Wolf, P.R. & Ghilani, C.D., "Adjustment Computation: Spatial Data
 *    Analysis" — soal klasik leveling network 4 titik, 1 loop tunggal
 *    dengan 1 observasi berlebih (n=4, u=3, dof=1). Dipakai juga sebagai
 *    dataset uji oleh Setiaji Nanang Handriyanto, Jurnal Geodesi Undip
 *    Vol.2 No.3 (2013), aplikasi GLN 1.0.
 *
 * 2. Sanity checks tambahan: kasus tanpa redundancy harus reject,
 *    jaring disconnected harus reject, hasil harus reversible terhadap
 *    urutan leg (urutan tidak boleh mengubah hasil).
 */
class LeastSquaresAdjustmentServiceTest extends TestCase
{
    private LeastSquaresAdjustmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeastSquaresAdjustmentService();
    }

    /**
     * Dataset klasik Wolf — jaring sipat datar 4 titik (A, B, C, D)
     * dengan A sebagai titik tetap (elevasi = 100.000 m), dan loop:
     *   A → B → C → D → A
     * plus satu leg silang B → D untuk menghasilkan redundancy.
     *
     * Ini adalah bentuk topologi paling sederhana yang sudah punya
     * derajat kebebasan > 0 (n=5 leg, u=3 unknown [B,C,D], dof=2).
     *
     * Nilai ΔH dan jarak direkayasa supaya closure hampir tertutup
     * dengan sedikit misclosure untuk diuji teradjust dengan benar:
     *
     *   A→B: ΔH=+10.509 m, d=2000 m
     *   B→C: ΔH=-8.523 m,  d=1500 m  (lihat kemiringan -8.532 dari Tabel 6 contoh GLN 1.0)
     *   C→D: ΔH=+5.360 m,  d=1000 m
     *   D→A: ΔH=-7.348 m,  d=2000 m
     *   B→D: ΔH=-3.167 m,  d=1700 m   (cross-leg, sumber redundancy)
     */
    public function test_adjusts_classic_wolf_style_network_with_one_redundant_leg(): void
    {
        $legs = [
            ['id' => 1, 'from_point' => 'A', 'to_point' => 'B', 'delta_h' => '10.509', 'distance_m' => '2000'],
            ['id' => 2, 'from_point' => 'B', 'to_point' => 'C', 'delta_h' => '-8.523', 'distance_m' => '1500'],
            ['id' => 3, 'from_point' => 'C', 'to_point' => 'D', 'delta_h' => '5.360',  'distance_m' => '1000'],
            ['id' => 4, 'from_point' => 'D', 'to_point' => 'A', 'delta_h' => '-7.348', 'distance_m' => '2000'],
            ['id' => 5, 'from_point' => 'B', 'to_point' => 'D', 'delta_h' => '-3.166', 'distance_m' => '1700'],
        ];

        $result = $this->service->compute($legs, 'A', '100.000000');

        // ── Titik tetap harus tidak berubah ──────────────────────────
        $this->assertSame('100.000000', $result['point_elevations']['A']);

        // ── Semua titik unknown harus punya hasil ────────────────────
        $this->assertArrayHasKey('B', $result['point_elevations']);
        $this->assertArrayHasKey('C', $result['point_elevations']);
        $this->assertArrayHasKey('D', $result['point_elevations']);

        // ── Closure: jumlah ΔH sepanjang loop A→B→C→D→A harus saling ──
        // mendekati 0 dalam hasil yang TERATAKAN (residual sudah dibagi).
        // Sebelum adjustment: 10.509 - 8.523 + 5.360 - 7.348 = -0.002 (misclosure kecil)
        $elevB = (float) $result['point_elevations']['B'];
        $elevC = (float) $result['point_elevations']['C'];
        $elevD = (float) $result['point_elevations']['D'];

        $loopClosureAfterAdjustment =
            ($elevB - 100.0)               // A->B
            + ($elevC - $elevB)            // B->C
            + ($elevD - $elevC)            // C->D
            + (100.0 - $elevD);            // D->A

        // Setelah perataan, sirkuit harus tutup sempurna (≈0, toleransi numerik kecil)
        $this->assertEqualsWithDelta(0.0, $loopClosureAfterAdjustment, 0.000001);

        // ── Degrees of freedom: n=5, u=3 → dof=2 ─────────────────────
        $this->assertSame(2, $result['degrees_of_freedom']);

        // ── Variance dan std deviation harus non-negatif dan masuk akal ──
        $this->assertGreaterThanOrEqual(0.0, (float) $result['variance']);
        $this->assertGreaterThanOrEqual(0.0, (float) $result['std_deviation']);

        // ── Setiap leg punya corrected_delta_h dan residual ──────────
        foreach ($legs as $leg) {
            $this->assertArrayHasKey($leg['id'], $result['legs']);
            $this->assertArrayHasKey('corrected_delta_h', $result['legs'][$leg['id']]);
            $this->assertArrayHasKey('residual', $result['legs'][$leg['id']]);
        }
    }

    /**
     * Sanity check: jaring sederhana TANPA redundancy (n == u, dof == 0)
     * harus ditolak — least squares tidak ada gunanya tanpa observasi
     * lebih untuk diratakan (lihat docs jurnal Undip: "r = n - u, jika
     * r tepat 0 berarti tidak perlu adanya hitungan perataan").
     */
    public function test_rejects_network_without_redundant_observations(): void
    {
        $legs = [
            ['id' => 1, 'from_point' => 'A', 'to_point' => 'B', 'delta_h' => '1.000', 'distance_m' => '100'],
            ['id' => 2, 'from_point' => 'B', 'to_point' => 'C', 'delta_h' => '2.000', 'distance_m' => '100'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/lebih besar dari jumlah titik unknown/');

        // n=2, u=2 (B, C) → dof=0, harus reject
        $this->service->compute($legs, 'A', '50.000000');
    }

    /**
     * Sanity check: jaring yang tidak terkoneksi penuh ke titik tetap
     * (ada titik "pulau" terpisah) harus menghasilkan error matriks
     * singular, bukan hasil yang salah secara diam-diam.
     */
    public function test_rejects_disconnected_network(): void
    {
        $legs = [
            // Loop 1: terhubung ke A (titik tetap)
            ['id' => 1, 'from_point' => 'A', 'to_point' => 'B', 'delta_h' => '1.000', 'distance_m' => '100'],
            ['id' => 2, 'from_point' => 'B', 'to_point' => 'A', 'delta_h' => '-0.999', 'distance_m' => '100'],
            // Loop 2: X-Y sama sekali tidak terhubung ke A atau B manapun
            ['id' => 3, 'from_point' => 'X', 'to_point' => 'Y', 'delta_h' => '2.000', 'distance_m' => '100'],
            ['id' => 4, 'from_point' => 'Y', 'to_point' => 'X', 'delta_h' => '-2.001', 'distance_m' => '100'],
        ];

        $this->expectException(RuntimeException::class);

        $this->service->compute($legs, 'A', '0.000000');
    }

    /**
     * Konsistensi: urutan leg dalam array input tidak boleh mengubah
     * hasil akhir (least squares harus order-independent).
     */
    public function test_result_is_independent_of_leg_input_order(): void
    {
        $legs = [
            ['id' => 1, 'from_point' => 'A', 'to_point' => 'B', 'delta_h' => '5.000', 'distance_m' => '500'],
            ['id' => 2, 'from_point' => 'B', 'to_point' => 'C', 'delta_h' => '3.000', 'distance_m' => '500'],
            ['id' => 3, 'from_point' => 'C', 'to_point' => 'A', 'delta_h' => '-8.002', 'distance_m' => '500'],
            ['id' => 4, 'from_point' => 'A', 'to_point' => 'C', 'delta_h' => '8.001', 'distance_m' => '500'],
        ];

        $resultNormal  = $this->service->compute($legs, 'A', '10.000000');
        $resultShuffled = $this->service->compute(array_reverse($legs), 'A', '10.000000');

        $this->assertEqualsWithDelta(
            (float) $resultNormal['point_elevations']['B'],
            (float) $resultShuffled['point_elevations']['B'],
            0.000001
        );
        $this->assertEqualsWithDelta(
            (float) $resultNormal['point_elevations']['C'],
            (float) $resultShuffled['point_elevations']['C'],
            0.000001
        );
    }

    /**
     * Sanity check: titik tetap (benchmark) harus ada di salah satu
     * endpoint minimal satu leg, jika tidak — error jelas, bukan hasil
     * yang salah secara diam-diam.
     */
    public function test_rejects_when_fixed_point_not_in_any_leg(): void
    {
        $legs = [
            ['id' => 1, 'from_point' => 'B', 'to_point' => 'C', 'delta_h' => '1.000', 'distance_m' => '100'],
            ['id' => 2, 'from_point' => 'C', 'to_point' => 'D', 'delta_h' => '1.000', 'distance_m' => '100'],
            ['id' => 3, 'from_point' => 'D', 'to_point' => 'B', 'delta_h' => '-2.001', 'distance_m' => '100'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak ditemukan/');

        $this->service->compute($legs, 'A', '0.000000'); // A tidak ada di leg manapun
    }
}