<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Models\CareEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Watering cadence against observed health: for each health observation, the interval between
 * the two most recent waterings on or before it, paired with the observation's overall health.
 * Pooled across the plants in a group.
 */
final class WateringIntervalFactor implements Factor
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'watering_interval_days';
    }

    /**
     * @return string
     */
    public function outcomeKey(): string
    {
        return 'overall_health';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['wateringEvents', 'observationEvents.observation'];
    }

    /**
     * @param Collection<int, Plant> $plants
     *
     * @return list<array{x: float, y: float}>
     */
    public function pairs(Collection $plants): array
    {
        $pairs = [];

        foreach ($plants as $plant) {
            $waterings = $plant->wateringEvents
                ->map(fn (CareEvent $event): Carbon => $event->occurred_at)
                ->sort()
                ->values();

            foreach ($plant->observationEvents as $event) {
                $health = $event->observation?->overall_health;

                if ($health === null) {
                    continue;
                }

                $prior = $waterings->filter(fn (Carbon $w): bool => $w <= $event->occurred_at)->values()->all();
                $count = count($prior);

                if ($count < 2) {
                    continue;
                }

                $interval = (int) round(
                    ($prior[$count - 1]->getTimestamp() - $prior[$count - 2]->getTimestamp()) / 86400,
                );

                if ($interval <= 0) {
                    continue;
                }

                $pairs[] = ['x' => (float) $interval, 'y' => (float) $health];
            }
        }

        return $pairs;
    }
}
