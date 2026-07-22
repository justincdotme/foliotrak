<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use Illuminate\Support\Collection;

/**
 * Ambient humidity against observed health: one pair per observation where both
 * ambient_humidity_pct and overall_health are recorded. Pooled across the plant group.
 */
final class HumidityFactor implements Factor
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'ambient_humidity_pct';
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
        return ['observationEvents.observation'];
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
            foreach ($plant->observationEvents as $event) {
                $humidity = $event->observation?->ambient_humidity_pct;
                $health   = $event->observation?->overall_health;

                if ($humidity === null || $health === null) {
                    continue;
                }

                $pairs[] = ['x' => (float) $humidity, 'y' => (float) $health];
            }
        }

        return $pairs;
    }
}
