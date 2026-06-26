<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CareEvent;
use App\Models\Observation;
use Closure;
use Illuminate\Support\Collection;

class Trends
{
    /**
     * @param  Collection<int, CareEvent>  $observationEvents
     * @return list<array{date: string, value: int|string|null}>
     */
    public static function health(Collection $observationEvents): array
    {
        return self::series($observationEvents, fn (Observation $observation) => $observation->overall_health);
    }

    /**
     * @param  Collection<int, CareEvent>  $observationEvents
     * @return list<array{date: string, value: int|string|null}>
     */
    public static function weight(Collection $observationEvents): array
    {
        return self::series($observationEvents, fn (Observation $observation) => $observation->weight_grams);
    }

    /**
     * @param  Collection<int, CareEvent>  $observationEvents
     * @return list<array{date: string, value: int|string|null}>
     */
    public static function growth(Collection $observationEvents): array
    {
        return self::series($observationEvents, fn (Observation $observation) => $observation->growth_rate?->value);
    }

    /**
     * @param  Collection<int, CareEvent>  $events
     * @param  Closure(Observation): (int|string|null)  $value
     * @return list<array{date: string, value: int|string|null}>
     */
    private static function series(Collection $events, Closure $value): array
    {
        return $events
            ->sortBy('occurred_at')
            ->map(function (CareEvent $event) use ($value): array {
                $observation = $event->observation;

                return [
                    'date' => $event->occurred_at->format('Y-m-d'),
                    'value' => $observation === null ? null : $value($observation),
                ];
            })
            ->values()
            ->all();
    }
}
