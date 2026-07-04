<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Models\Symptom;
use Illuminate\Support\Carbon;

/**
 * Walks a plant's observation history to detect symptom episodes: periods where a
 * symptom was present and subsequently cleared. The caller must eager-load
 * `observationEvents.observation.symptoms` before calling this.
 */
final class SymptomEpisodeResolver
{
    /**
     * @return list<array{symptom_key: string, symptom_label: string, category: string, appeared_at: string, cleared_at: string|null, duration_days: int|null, health_at_appear: int|null, health_at_clear: int|null}>
     */
    public static function forPlant(Plant $plant): array
    {
        $events = $plant->observationEvents
            ->sort(function (CareEvent $a, CareEvent $b): int {
                $diff = $a->occurred_at->getTimestamp() - $b->occurred_at->getTimestamp();

                return $diff !== 0 ? $diff : $a->id - $b->id;
            })
            ->values();

        if ($events->count() < 2) {
            return [];
        }

        /** @var array<string, array{appeared_at: Carbon, health_at_appear: int|null, symptom: Symptom}> $active */
        $active = [];
        $episodes = [];

        foreach ($events as $event) {
            $observation = $event->observation;
            $currentSymptoms = $observation !== null ? $observation->symptoms : collect();
            $currentByKey = $currentSymptoms->keyBy('key');
            $health = $observation !== null ? $observation->overall_health : null;
            $health = $health !== null ? (int) $health : null;

            foreach ($currentSymptoms as $symptom) {
                if (! array_key_exists($symptom->key, $active)) {
                    $active[$symptom->key] = [
                        'appeared_at' => $event->occurred_at,
                        'health_at_appear' => $health,
                        'symptom' => $symptom,
                    ];
                }
            }

            foreach (array_keys($active) as $activeKey) {
                if (! $currentByKey->has($activeKey)) {
                    $entry = $active[$activeKey];
                    $episodes[] = [
                        'symptom_key' => $activeKey,
                        'symptom_label' => $entry['symptom']->label,
                        'category' => $entry['symptom']->category->value,
                        'appeared_at' => $entry['appeared_at']->toDateString(),
                        'cleared_at' => $event->occurred_at->toDateString(),
                        'duration_days' => (int) $entry['appeared_at']->diffInDays($event->occurred_at),
                        'health_at_appear' => $entry['health_at_appear'],
                        'health_at_clear' => $health,
                    ];
                    unset($active[$activeKey]);
                }
            }
        }

        foreach ($active as $key => $entry) {
            $episodes[] = [
                'symptom_key' => $key,
                'symptom_label' => $entry['symptom']->label,
                'category' => $entry['symptom']->category->value,
                'appeared_at' => $entry['appeared_at']->toDateString(),
                'cleared_at' => null,
                'duration_days' => null,
                'health_at_appear' => $entry['health_at_appear'],
                'health_at_clear' => null,
            ];
        }

        return $episodes;
    }
}
