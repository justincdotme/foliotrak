<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Plant;
use App\Support\Correlation\Factor;
use App\Support\Correlation\WateringIntervalFactor;
use Illuminate\Support\Collection;

/**
 * Pools each registered numeric factor across a set of plants and reports it as a correlation
 * pair: Spearman rho, a t-test p-value, a Fisher-z band, the raw points, and a Benjamini-Hochberg
 * significance flag applied across every tested pair (ADR-0020). A factor with too few pooled
 * samples to be meaningful is omitted rather than shown with a misleading coefficient.
 */
final class CorrelationEngine
{
    private const MIN_SAMPLES = 5;

    /**
     * @param  Collection<int, Plant>  $plants
     * @param  list<Factor>|null  $factors
     * @return list<array{x_variable: string, y_variable: string, correlation: float, p_value: float, sample_size: int, confidence_band: array{lower: float, upper: float}, significant_after_fdr: bool, points: list<array{x: float, y: float}>}>
     */
    public static function forPlants(Collection $plants, ?array $factors = null): array
    {
        $factors ??= self::defaultFactors();

        $pairs = [];
        foreach ($factors as $factor) {
            $samples = $factor->pairs($plants);
            $n = count($samples);
            if ($n < self::MIN_SAMPLES) {
                continue;
            }

            $x = array_map(fn (array $sample): float => $sample['x'], $samples);
            $y = array_map(fn (array $sample): float => $sample['y'], $samples);
            $rho = Stats::spearman($x, $y);

            $pairs[] = [
                'x_variable' => $factor->key(),
                'y_variable' => $factor->outcomeKey(),
                'correlation' => round($rho, 4),
                'p_value' => round(Stats::spearmanPValue($rho, $n), 4),
                'sample_size' => $n,
                'confidence_band' => Stats::fisherConfidenceBand($rho, $n),
                'significant_after_fdr' => false,
                'points' => array_map(
                    fn (array $sample): array => ['x' => $sample['x'], 'y' => $sample['y']],
                    $samples,
                ),
            ];
        }

        $significant = Stats::benjaminiHochberg(array_map(fn (array $pair): float => $pair['p_value'], $pairs));
        foreach ($pairs as $index => $pair) {
            $pairs[$index]['significant_after_fdr'] = $significant[$index] ?? false;
        }

        return $pairs;
    }

    /**
     * The eager-load paths (relative to a plant) the default factors read, so the caller loads
     * them up front and no factor lazy-loads per plant.
     *
     * @return list<string>
     */
    public static function plantRelations(): array
    {
        $relations = [];
        foreach (self::defaultFactors() as $factor) {
            foreach ($factor->relations() as $relation) {
                $relations[] = $relation;
            }
        }

        return array_values(array_unique($relations));
    }

    /**
     * @return list<Factor>
     */
    private static function defaultFactors(): array
    {
        return [new WateringIntervalFactor()];
    }
}
