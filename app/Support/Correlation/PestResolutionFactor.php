<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Support\SymptomEpisodeResolver;
use Illuminate\Support\Collection;

/**
 * Resolution time against health at clear: for each resolved symptom episode, the
 * number of days the symptom persisted paired with the overall health recorded when
 * it cleared. Pooled across the plants in a group.
 */
final class PestResolutionFactor implements Factor
{
    /**
     * @return string
     */
    public function key(): string
    {
        return 'resolution_time_days';
    }

    /**
     * @return string
     */
    public function outcomeKey(): string
    {
        return 'health_at_clear';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['observationEvents.observation.symptoms'];
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
            foreach (SymptomEpisodeResolver::forPlant($plant) as $episode) {
                if ($episode['cleared_at'] === null || $episode['health_at_clear'] === null) {
                    continue;
                }

                $pairs[] = [
                    'x' => (float) $episode['duration_days'],
                    'y' => (float) $episode['health_at_clear'],
                ];
            }
        }

        return $pairs;
    }
}
