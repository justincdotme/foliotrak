<?php

declare(strict_types=1);

namespace App\Support;

use MathPHP\Probability\Distribution\Continuous\StudentT;
use MathPHP\Statistics\Average;
use MathPHP\Statistics\Correlation;

/**
 * Small-sample statistics for the insight engine: Spearman rank correlation with a
 * t-test p-value and a Fisher-z confidence band, plus Benjamini-Hochberg false-discovery
 * control. Reported with sample size and uncertainty, never as a causal claim (spec 8).
 */
final class Stats
{
    /**
     * @param list<int|float> $values
     *
     * @return float|null
     */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return (float) Average::median($values);
    }

    /**
     * @param list<int|float> $x
     * @param list<int|float> $y
     *
     * @return float
     */
    public static function spearman(array $x, array $y): float
    {
        if (count($x) < 2 || count($x) !== count($y)) {
            return 0.0;
        }

        // A constant series has zero rank variance, so Spearman's denominator is 0 and math-php
        // throws a division error. No spread means no usable signal, so report no correlation.
        if (count(array_unique($x)) < 2 || count(array_unique($y)) < 2) {
            return 0.0;
        }

        return (float) Correlation::spearmansRho($x, $y);
    }

    /**
     * Two-tailed p-value for a Spearman rho via the t approximation. Returns 1.0 when the
     * sample is too small to test (fewer than three pairs), and 0.0 at a perfect monotonic fit.
     *
     * @param float   $rho
     * @param integer $n
     *
     * @return float
     */
    public static function spearmanPValue(float $rho, int $n): float
    {
        $df = $n - 2;

        if ($df < 1) {
            return 1.0;
        }

        if ($rho * $rho >= 1.0) {
            return 0.0;
        }

        $t = $rho * sqrt($df / (1 - $rho * $rho));
        $p = 2 * (1 - (new StudentT($df))->cdf(abs($t)));

        return max(0.0, min(1.0, $p));
    }

    /**
     * Fisher z-transform confidence band for a Spearman rho. Below four pairs the standard
     * error is undefined, so the band widens to the full [-1, 1] range to read as no information.
     *
     * @param float   $rho
     * @param integer $n
     * @param float   $z
     *
     * @return array{lower: float, upper: float}
     */
    public static function fisherConfidenceBand(float $rho, int $n, float $z = 1.96): array
    {
        if ($n <= 3) {
            return ['lower' => -1.0, 'upper' => 1.0];
        }

        $clamped = max(-0.999999, min(0.999999, $rho));
        $zr      = atanh($clamped);
        $se      = 1 / sqrt($n - 3);

        return [
            'lower' => round(tanh($zr - $z * $se), 4),
            'upper' => round(tanh($zr + $z * $se), 4),
        ];
    }

    /**
     * Benjamini-Hochberg false-discovery control. Returns, in the input order, whether each
     * hypothesis is rejected (significant) at the given FDR level once the whole family is
     * considered, so a single chance correlation among many is not paraded as a finding.
     *
     * @param list<float> $pValues
     * @param float       $alpha
     *
     * @return list<bool>
     */
    public static function benjaminiHochberg(array $pValues, float $alpha = 0.05): array
    {
        $count = count($pValues);

        if ($count === 0) {
            return [];
        }

        $ranked = [];

        foreach ($pValues as $index => $p) {
            $ranked[] = ['index' => $index, 'p' => $p];
        }
        usort($ranked, fn (array $a, array $b): int => $a['p'] <=> $b['p']);

        $maxRank = 0;

        foreach ($ranked as $position => $entry) {
            $rank = $position + 1;

            if ($entry['p'] <= ($rank / $count) * $alpha) {
                $maxRank = $rank;
            }
        }

        $significant = array_fill(0, $count, false);

        foreach ($ranked as $position => $entry) {
            if ($position + 1 <= $maxRank) {
                $significant[$entry['index']] = true;
            }
        }

        return $significant;
    }
}
