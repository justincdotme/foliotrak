<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Stats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int|float>, list<int|float>, float}>
     */
    public static function spearmanCases(): iterable
    {
        yield 'perfect increasing' => [[1, 2, 3, 4, 5], [10, 20, 30, 40, 50], 1.0];
        yield 'perfect decreasing' => [[1, 2, 3, 4, 5], [50, 40, 30, 20, 10], -1.0];
        yield 'monotonic with a swap' => [[1, 2, 3, 4, 5], [2, 1, 4, 3, 6], 0.8];
        yield 'too few pairs short-circuits to zero' => [[1], [2], 0.0];
        yield 'mismatched lengths short-circuit to zero' => [[1, 2, 3], [1, 2], 0.0];
        yield 'constant x (a fixed cadence) reports no correlation, not a crash' => [[7, 7, 7, 7, 7], [1, 2, 3, 4, 5], 0.0];
        yield 'constant y (a flat health log) reports no correlation, not a crash' => [[1, 2, 3, 4, 5], [4, 4, 4, 4, 4], 0.0];
    }

    /**
     * @return iterable<string, array{list<float>, list<bool>}>
     */
    public static function fdrCases(): iterable
    {
        yield 'empty family' => [[], []];
        yield 'all clearly significant' => [[0.001, 0.002], [true, true]];
        yield 'none significant' => [[0.5, 0.9], [false, false]];
        yield 'rejects only the smallest, preserving input order' => [[0.04, 0.005, 0.5], [false, true, false]];
        yield 'step-up rescues a middling p when a larger rank passes' => [[0.001, 0.04, 0.045], [true, true, true]];
    }

    /**
     * @param list<int|float> $x
     * @param list<int|float> $y
     * @param float           $expected
     *
     * @return void
     */
    #[DataProvider('spearmanCases')]
    public function test_spearman_ranks_monotonic_relationships(array $x, array $y, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Stats::spearman($x, $y), 0.0001);
    }

    /** @return void */
    public function test_spearman_p_value_is_zero_at_a_perfect_fit_and_one_when_untestable(): void
    {
        $this->assertSame(0.0, Stats::spearmanPValue(1.0, 8));
        $this->assertSame(0.0, Stats::spearmanPValue(-1.0, 8));
        $this->assertSame(1.0, Stats::spearmanPValue(0.6, 2));
    }

    /** @return void */
    public function test_spearman_p_value_falls_in_the_unit_interval_and_shrinks_with_stronger_correlation(): void
    {
        $weak   = Stats::spearmanPValue(0.3, 12);
        $strong = Stats::spearmanPValue(0.8, 12);

        $this->assertGreaterThan(0.0, $weak);
        $this->assertLessThan(1.0, $weak);
        $this->assertLessThan($weak, $strong);
    }

    /** @return void */
    public function test_fisher_band_widens_to_full_range_below_four_pairs(): void
    {
        $this->assertSame(['lower' => -1.0, 'upper' => 1.0], Stats::fisherConfidenceBand(0.5, 3));
    }

    /** @return void */
    public function test_fisher_band_brackets_the_estimate_inside_the_valid_range(): void
    {
        $band = Stats::fisherConfidenceBand(0.5, 30);

        $this->assertGreaterThanOrEqual(-1.0, $band['lower']);
        $this->assertLessThanOrEqual(1.0, $band['upper']);
        $this->assertLessThan(0.5, $band['lower']);
        $this->assertGreaterThan(0.5, $band['upper']);
    }

    /**
     * @param list<float>   $pValues
     * @param list<boolean> $expected
     *
     * @return void
     */
    #[DataProvider('fdrCases')]
    public function test_benjamini_hochberg_controls_false_discovery(array $pValues, array $expected): void
    {
        $this->assertSame($expected, Stats::benjaminiHochberg($pValues));
    }

    /** @return void */
    public function test_median_handles_even_odd_and_empty(): void
    {
        $this->assertSame(3.0, Stats::median([1, 5, 3]));
        $this->assertSame(2.5, Stats::median([1, 2, 3, 4]));
        $this->assertNull(Stats::median([]));
    }
}
