<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Models\Plant;
use Illuminate\Support\Collection;

/**
 * Recorded light level against observed health: one pair per observation where both
 * light_level and overall_health are recorded. Pooled across the plant group.
 */
final class LightLevelFactor implements Factor
{
    public function key(): string
    {
        return 'light_level';
    }

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
     * @param  Collection<int, Plant>  $plants
     * @return list<array{x: float, y: float}>
     */
    public function pairs(Collection $plants): array
    {
        $pairs = [];

        foreach ($plants as $plant) {
            foreach ($plant->observationEvents as $event) {
                $lightLevel = $event->observation?->light_level;
                $health = $event->observation?->overall_health;

                if ($lightLevel === null || $health === null) {
                    continue;
                }

                $pairs[] = ['x' => (float) $lightLevel, 'y' => (float) $health];
            }
        }

        return $pairs;
    }
}
