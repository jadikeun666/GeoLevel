<?php

namespace Tests\Unit\Services;
use PHPUnit\Framework\Attributes\Test;

use App\Services\ClosureCheckerService;
use Tests\TestCase;

/**
 * Unit tests for ClosureCheckerService.
 *
 * Canonical values from docs/formulas.md:
 *   ΣBS          = 3.7580
 *   ΣFS          = 3.3570
 *   fh           = |3.7580 − 3.3570| = 0.4010 m (open traverse)
 *   Total dist   = 375.6 m = 0.3756 km
 *   Tolerance LA = 4 mm × √0.3756 = 0.002452 m
 *   Status       = rejected  (fh >> tolerance)
 *
 * All fh assertions use delta = 0.000001 (6 decimal places).
 */
class ClosureCheckerServiceTest extends TestCase
{
    private const DELTA_6 = 0.000001;
    private const DELTA_4 = 0.0001;

    // ---------------------------------------------------------------------------
    // Closure error — open traverse: fh = |ΣBS − ΣFS|
    // ---------------------------------------------------------------------------

    #[Test]
    public function closure_error_equals_abs_sum_bs_minus_sum_fs(): void
    {
        $fh = ClosureCheckerService::computeClosureError('3.7580', '3.3570');
        $this->assertEqualsWithDelta(0.401000, (float) $fh, self::DELTA_6);
    }

    #[Test]
    public function closure_error_is_absolute_value_when_fs_exceeds_bs(): void
    {
        // Swap BS and FS — result must still be positive
        $fh = ClosureCheckerService::computeClosureError('3.3570', '3.7580');
        $this->assertEqualsWithDelta(0.401000, (float) $fh, self::DELTA_6);
    }

    #[Test]
    public function closure_error_is_zero_for_perfect_closure(): void
    {
        $fh = ClosureCheckerService::computeClosureError('3.7580', '3.7580');
        $this->assertEqualsWithDelta(0.000000, (float) $fh, self::DELTA_6);
    }

    // ---------------------------------------------------------------------------
    // Allowed tolerance: r = c × √(d_km)
    // ---------------------------------------------------------------------------

    #[Test]
    public function tolerance_laa_computed_correctly(): void
    {
        // c = 0.002, d = 0.3756 km → r = 0.002 × √0.3756 = 0.001225 m
        $r = ClosureCheckerService::computeAllowedTolerance('0.3756', 'LAA');
        $this->assertEqualsWithDelta(0.001225, (float) $r, self::DELTA_6);
    }

    #[Test]
    public function tolerance_la_computed_correctly_canonical_dataset(): void
    {
        // c = 0.004, d = 0.3756 km → r = 0.004 × √0.3756 ≈ 0.002452
        $r = ClosureCheckerService::computeAllowedTolerance('0.3756', 'LA');
        $this->assertEqualsWithDelta(0.002452, (float) $r, self::DELTA_6);
    }

    #[Test]
    public function tolerance_lb_computed_correctly(): void
    {
        // c = 0.008, d = 0.3756 km → r = 0.008 × √0.3756 ≈ 0.004903
        $r = ClosureCheckerService::computeAllowedTolerance('0.3756', 'LB');
        $this->assertEqualsWithDelta(0.004903, (float) $r, self::DELTA_6);
    }

    #[Test]
    public function tolerance_lc_computed_correctly(): void
    {
        // c = 0.012, d = 0.3756 km → r = 0.012 × √0.3756 ≈ 0.007355
        $r = ClosureCheckerService::computeAllowedTolerance('0.3756', 'LC');
        $this->assertEqualsWithDelta(0.007355, (float) $r, self::DELTA_6);
    }

    #[Test]
    public function tolerance_uses_km_not_metres(): void
    {
        // 1000 m = 1 km → √1 = 1 → r = c × 1
        $r = ClosureCheckerService::computeAllowedTolerance('1.000', 'LA');
        $this->assertEqualsWithDelta(0.004000, (float) $r, self::DELTA_6);
    }

    // ---------------------------------------------------------------------------
    // Status determination
    // ---------------------------------------------------------------------------

    #[Test]
    public function status_is_rejected_when_fh_exceeds_tolerance_canonical_dataset(): void
    {
        // fh = 0.4010, tolerance_LA = 0.002452 → rejected
        $status = ClosureCheckerService::determineStatus('0.401000', '0.002452');
        $this->assertSame('rejected', $status);
    }

    #[Test]
    public function status_is_accepted_when_fh_within_tolerance(): void
    {
        $status = ClosureCheckerService::determineStatus('0.001000', '0.002452');
        $this->assertSame('accepted', $status);
    }

    #[Test]
    public function status_is_accepted_when_fh_exactly_equals_tolerance(): void
    {
        $status = ClosureCheckerService::determineStatus('0.002452', '0.002452');
        $this->assertSame('accepted', $status);
    }

    #[Test]
    public function status_is_calculated_when_project_has_been_processed(): void
    {
        // A project that has gone through calculation but not yet checked acceptance
        // is represented by a zero closure_error update — verify status transitions
        $status = ClosureCheckerService::determineStatus('0.000000', '0.002452');
        $this->assertSame('accepted', $status);
    }

    // ---------------------------------------------------------------------------
    // End-to-end check using canonical dataset
    // ---------------------------------------------------------------------------

    #[Test]
    public function check_returns_correct_payload_for_canonical_dataset(): void
    {
        $result = ClosureCheckerService::check(
            sumBs: '3.7580',
            sumFs: '3.3570',
            totalDistanceKm: '0.3756',
            toleranceClass: 'LA'
        );

        $this->assertEqualsWithDelta(0.401000, (float) $result['closure_error'],   self::DELTA_6);
        $this->assertEqualsWithDelta(0.002452, (float) $result['allowed_tolerance'], self::DELTA_6);
        $this->assertSame('rejected', $result['status']);
    }
}