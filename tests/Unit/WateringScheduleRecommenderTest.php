<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SoilMoistureLevel;
use App\Support\WateringScheduleRecommender;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class WateringScheduleRecommenderTest extends TestCase
{
    /** @var Carbon */
    private Carbon $now;

    /** @var Carbon */
    private Carbon $earliest;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        $this->now      = Carbon::parse('2026-06-26 09:00:00');
        $this->earliest = $this->now->copy()->subDays(120);
    }

    /** @return void */
    public function test_fewer_than_two_waterings_yields_no_recommendation(): void
    {
        $this->assertNull(
            WateringScheduleRecommender::recommend([$this->now->copy()], [], [], $this->earliest, $this->now),
        );
    }

    /** @return void */
    public function test_a_steady_cadence_recommends_the_plain_median(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 7) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $result = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [200, 200, 300],
            $this->earliest,
            $this->now,
        );

        $this->assertNotNull($result);
        $this->assertSame('stable', $result['basis']);
        $this->assertSame(7, $result['interval_days']);
        $this->assertSame(200, $result['amount_ml']);
        $this->assertStringContainsString('every 7 days', $result['rationale']);
    }

    /** @return void */
    public function test_a_decline_after_slowing_down_recommends_reverting_to_the_healthier_cadence(): void
    {
        $result = WateringScheduleRecommender::recommend(
            [
                $this->earliest->copy(), $this->day(6), $this->day(12), $this->day(18), $this->day(24),
                $this->beforeNow(24), $this->beforeNow(12), $this->now->copy(),
            ],
            [
                $this->obs(5, 5), $this->obs(10, 5), $this->obs(15, 4),
                $this->obsBeforeNow(20, 2), $this->obsBeforeNow(10, 2), $this->obsBeforeNow(2, 3),
            ],
            [],
            $this->earliest,
            $this->now,
        );

        $this->assertNotNull($result);
        $this->assertSame('revert', $result['basis']);
        $this->assertSame(6, $result['interval_days']);
        $this->assertSame(6, $result['baseline_interval_days']);
        $this->assertSame(12, $result['recent_interval_days']);
        $this->assertSame(8, $result['sample_size']);
        $this->assertSame(6, $result['health_sample_size']);
        $this->assertStringContainsString('returning to about every 6 days', $result['rationale']);
        $this->assertStringNotContainsStringIgnoringCase('caused', $result['rationale']);
    }

    /** @return void */
    public function test_an_improvement_after_speeding_up_recommends_keeping_the_recent_cadence(): void
    {
        $result = WateringScheduleRecommender::recommend(
            [
                $this->earliest->copy(), $this->day(12), $this->day(24),
                $this->beforeNow(18), $this->beforeNow(12), $this->beforeNow(6), $this->now->copy(),
            ],
            [
                $this->obs(5, 2), $this->obs(15, 2),
                $this->obsBeforeNow(15, 5), $this->obsBeforeNow(8, 5), $this->obsBeforeNow(2, 4),
            ],
            [],
            $this->earliest,
            $this->now,
        );

        $this->assertNotNull($result);
        $this->assertSame('maintain', $result['basis']);
        $this->assertSame(6, $result['interval_days']);
        $this->assertSame(12, $result['baseline_interval_days']);
        $this->assertSame(6, $result['recent_interval_days']);
        $this->assertStringContainsString('Worth keeping', $result['rationale']);
    }

    /** @return void */
    public function test_no_recent_waterings_falls_back_to_the_plain_median(): void
    {
        // All waterings sit inside the baseline window; the recent window has none to compare.
        $result = WateringScheduleRecommender::recommend(
            [$this->earliest->copy(), $this->day(6), $this->day(12), $this->day(18)],
            [$this->obs(5, 5), $this->obs(10, 2)],
            [],
            $this->earliest,
            $this->now,
        );

        $this->assertNotNull($result);
        $this->assertSame('stable', $result['basis']);
        $this->assertSame(6, $result['interval_days']);
    }

    /** @return void */
    public function test_a_young_plant_does_not_compare_overlapping_windows(): void
    {
        // Under 56 days, the baseline and recent windows overlap, so the two-window comparison is
        // skipped and the plain median is reported without claiming a revert or a steady cadence.
        $earliest = $this->now->copy()->subDays(40);

        $result = WateringScheduleRecommender::recommend(
            [$earliest->copy(), $earliest->copy()->addDays(6), $earliest->copy()->addDays(12), $this->now->copy()->subDays(10), $this->now->copy()],
            [['date' => $earliest->copy()->addDays(5), 'health' => 5], ['date' => $this->now->copy()->subDays(2), 'health' => 2]],
            [],
            $earliest,
            $this->now,
        );

        $this->assertNotNull($result);
        $this->assertSame('stable', $result['basis']);
        $this->assertNull($result['baseline_interval_days']);
        $this->assertNull($result['recent_interval_days']);
        $this->assertStringContainsString('overall median', $result['rationale']);
        $this->assertStringNotContainsString('returning', $result['rationale']);
    }

    /** @return void */
    public function test_a_small_cadence_drift_within_tolerance_stays_stable(): void
    {
        $result = WateringScheduleRecommender::recommend(
            [
                $this->earliest->copy(), $this->day(7), $this->day(14), $this->day(21), $this->day(28),
                $this->beforeNow(24), $this->beforeNow(16), $this->beforeNow(8), $this->now->copy(),
            ],
            [$this->obs(10, 4), $this->obsBeforeNow(10, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $this->assertNotNull($result);
        $this->assertSame('stable', $result['basis']);
        $this->assertStringContainsString('held about steady', $result['rationale']);
    }

    /** @return void */
    public function test_no_rationale_uses_causal_language(): void
    {
        $forbidden = ['caused', 'causes', 'leads to', 'because', 'due to', 'results in'];

        $rationales = [
            $this->rationaleFor(
                [$this->earliest->copy(), $this->day(6), $this->day(12), $this->day(18), $this->day(24), $this->beforeNow(24), $this->beforeNow(12), $this->now->copy()],
                [$this->obs(5, 5), $this->obs(10, 5), $this->obs(15, 4), $this->obsBeforeNow(20, 2), $this->obsBeforeNow(10, 2), $this->obsBeforeNow(2, 3)],
            ),
            $this->rationaleFor(
                [$this->earliest->copy(), $this->day(12), $this->day(24), $this->beforeNow(18), $this->beforeNow(12), $this->beforeNow(6), $this->now->copy()],
                [$this->obs(5, 2), $this->obs(15, 2), $this->obsBeforeNow(15, 5), $this->obsBeforeNow(8, 5), $this->obsBeforeNow(2, 4)],
            ),
            $this->rationaleFor(
                [$this->earliest->copy(), $this->day(7), $this->day(14), $this->day(21), $this->day(28), $this->beforeNow(21), $this->beforeNow(14), $this->beforeNow(7), $this->now->copy()],
                [$this->obs(10, 4), $this->obsBeforeNow(10, 4)],
            ),
            $this->rationaleFor(
                [$this->earliest->copy(), $this->day(6), $this->day(12), $this->day(18)],
                [$this->obs(5, 5), $this->obs(10, 2)],
            ),
        ];

        foreach ($rationales as $rationale) {
            foreach ($forbidden as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $rationale);
            }
        }
    }

    /** @return void */
    public function test_dry_soil_shortens_the_interval(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $base = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $withDrySoil = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 2, 'relative' => null], ['precise' => 2, 'relative' => null], ['precise' => 2, 'relative' => null]],
        );

        $this->assertNotNull($base);
        $this->assertNotNull($withDrySoil);
        $this->assertLessThan($base['interval_days'], $withDrySoil['interval_days']);
        $this->assertStringContainsString('dries out faster', $withDrySoil['rationale']);
    }

    /** @return void */
    public function test_wet_soil_lengthens_the_interval(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $base = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $withWetSoil = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 8, 'relative' => null], ['precise' => 8, 'relative' => null], ['precise' => 8, 'relative' => null]],
        );

        $this->assertNotNull($base);
        $this->assertNotNull($withWetSoil);
        $this->assertGreaterThan($base['interval_days'], $withWetSoil['interval_days']);
        $this->assertStringContainsString('retains moisture', $withWetSoil['rationale']);
    }

    /** @return void */
    public function test_soil_adjustment_is_capped_at_twenty_percent(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $base = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $extreme = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 10, 'relative' => null], ['precise' => 10, 'relative' => null], ['precise' => 10, 'relative' => null]],
        );

        $this->assertNotNull($base);
        $this->assertNotNull($extreme);
        $maxExtension = (int) round($base['interval_days'] * 1.20);
        $this->assertLessThanOrEqual($maxExtension, $extreme['interval_days']);
    }

    /** @return void */
    public function test_normal_soil_does_not_adjust_the_interval(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $base = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $neutral = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 5, 'relative' => null], ['precise' => 5, 'relative' => null], ['precise' => 5, 'relative' => null]],
        );

        $this->assertNotNull($base);
        $this->assertNotNull($neutral);
        $this->assertSame($base['interval_days'], $neutral['interval_days']);
    }

    /** @return void */
    public function test_soil_relative_enum_falls_back_when_precise_is_null(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $withDryRelative = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => null, 'relative' => SoilMoistureLevel::Dry], ['precise' => null, 'relative' => SoilMoistureLevel::Dry], ['precise' => null, 'relative' => SoilMoistureLevel::Dry]],
        );

        $this->assertNotNull($withDryRelative);
        $this->assertStringContainsString('dries out faster', $withDryRelative['rationale']);
    }

    /** @return void */
    public function test_precise_overrides_relative_when_both_are_set(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $base = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $preciseWins = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 5, 'relative' => SoilMoistureLevel::Dry], ['precise' => 5, 'relative' => SoilMoistureLevel::Dry], ['precise' => 5, 'relative' => SoilMoistureLevel::Dry]],
        );

        $this->assertNotNull($base);
        $this->assertNotNull($preciseWins);
        $this->assertSame($base['interval_days'], $preciseWins['interval_days']);
    }

    /** @return void */
    public function test_empty_soil_readings_leave_interval_unchanged(): void
    {
        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $base = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
        );

        $withEmpty = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [],
        );

        $this->assertNotNull($base);
        $this->assertNotNull($withEmpty);
        $this->assertSame($base['interval_days'], $withEmpty['interval_days']);
    }

    /** @return void */
    public function test_soil_rationale_uses_no_causal_language(): void
    {
        $forbidden = ['caused', 'causes', 'leads to', 'because', 'due to', 'results in'];

        $waterings = [];

        for ($day = 0; $day <= 119; $day += 10) {
            $waterings[] = $this->earliest->copy()->addDays($day);
        }

        $dry = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 2, 'relative' => null], ['precise' => 2, 'relative' => null], ['precise' => 2, 'relative' => null]],
        );

        $wet = WateringScheduleRecommender::recommend(
            $waterings,
            [$this->obs(10, 4), $this->obs(110, 4)],
            [],
            $this->earliest,
            $this->now,
            [['precise' => 8, 'relative' => null], ['precise' => 8, 'relative' => null], ['precise' => 8, 'relative' => null]],
        );

        $this->assertNotNull($dry);
        $this->assertNotNull($wet);

        foreach ([$dry['rationale'], $wet['rationale']] as $rationale) {
            foreach ($forbidden as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $rationale);
            }
        }
    }

    /**
     * @param list<Carbon>                           $waterings
     * @param list<array{date: Carbon, health: int}> $observations
     *
     * @return string
     */
    private function rationaleFor(array $waterings, array $observations): string
    {
        $result = WateringScheduleRecommender::recommend($waterings, $observations, [], $this->earliest, $this->now);
        $this->assertNotNull($result);

        return $result['rationale'];
    }

    /**
     * @param integer $offset
     *
     * @return Carbon
     */
    private function day(int $offset): Carbon
    {
        return $this->earliest->copy()->addDays($offset);
    }

    /**
     * @param integer $offset
     *
     * @return Carbon
     */
    private function beforeNow(int $offset): Carbon
    {
        return $this->now->copy()->subDays($offset);
    }

    /**
     * @param integer $daysFromEarliest
     * @param integer $health
     *
     * @return array{date: Carbon, health: int}
     */
    private function obs(int $daysFromEarliest, int $health): array
    {
        return ['date' => $this->earliest->copy()->addDays($daysFromEarliest), 'health' => $health];
    }

    /**
     * @param integer $daysBeforeNow
     * @param integer $health
     *
     * @return array{date: Carbon, health: int}
     */
    private function obsBeforeNow(int $daysBeforeNow, int $health): array
    {
        return ['date' => $this->now->copy()->subDays($daysBeforeNow), 'health' => $health];
    }
}
