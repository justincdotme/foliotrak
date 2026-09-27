<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Support\Care\DryingRateEstimator;
use App\Support\Care\SoilHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ambient humidity against how fast the soil actually dried, one pair per observed
 * drying run, shown with its sample size and uncertainty. Observation history only,
 * since a pooled correlation does not justify loading every plant's sensor readings.
 */
final class DryingRateHumidityFactor implements Factor
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'drying_rate_humidity';
    }

    /**
     * @return string
     */
    public function outcomeKey(): string
    {
        return 'drying_rate_per_day';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['observationEvents.observation', 'wateringEvents'];
    }

    /**
     * @param Collection<int, Plant> $plants
     *
     * @return list<array{x: float, y: float}>
     */
    public function pairs(Collection $plants): array
    {
        $pairs = [];
        $now   = Carbon::now();

        foreach ($plants as $plant) {
            $history = SoilHistory::for($plant, $now);
            $runs    = DryingRateEstimator::runs($history->daily(), $history->wateringTimes());

            foreach ($runs as $run) {
                if ($run->humidityPct === null) {
                    continue;
                }

                $pairs[] = ['x' => $run->humidityPct, 'y' => $run->perDay];
            }
        }

        return $pairs;
    }
}
