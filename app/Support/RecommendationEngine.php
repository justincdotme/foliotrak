<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CareEvent;
use App\Models\Plant;
use Illuminate\Support\Carbon;

/**
 * Assembles a plant's recommendations payload: the four-week gate, the health-aware watering
 * cadence and the per-plant "did moving help" insights. The gate is measured from the plant's
 * earliest care event of any type.
 */
final class RecommendationEngine
{
    public const GATE_DAYS = 28;

    /**
     * @return array{plant_id: int, gate: array{state: string, history_days: int, required_days: int, days_to_go: int}, watering: array<string, mixed>|null, position_insights: list<array<string, mixed>>, health_by_location: list<array{location: array{id: int, name: string}|null, median_health: float|null, sample_size: int, healths: list<int>}>}
     */
    public static function forPlant(Plant $plant): array
    {
        $now = Carbon::now();

        /** @var Carbon|null $earliest */
        $earliest = $plant->careEvents->min('occurred_at');
        $historyDays = $earliest === null
            ? 0
            : (int) floor(($now->getTimestamp() - $earliest->getTimestamp()) / 86400);

        $healthObservations = [];
        foreach ($plant->observationEvents as $event) {
            $health = $event->observation?->overall_health;
            if ($health !== null) {
                $healthObservations[] = ['date' => $event->occurred_at, 'health' => (int) $health];
            }
        }

        $state = self::state($historyDays, $healthObservations);

        return [
            'plant_id' => $plant->id,
            'gate' => [
                'state' => $state,
                'history_days' => $historyDays,
                'required_days' => self::GATE_DAYS,
                'days_to_go' => max(0, self::GATE_DAYS - $historyDays),
            ],
            'watering' => $state === 'ready' && $earliest !== null
                ? self::watering($plant, $healthObservations, $earliest, $now)
                : null,
            'position_insights' => PositionInsight::forMoves(self::moves($plant), $healthObservations),
            'health_by_location' => LocationHealthInsight::forPlant(
                self::moves($plant),
                $healthObservations,
                $plant->location ? ['id' => $plant->location->id, 'name' => $plant->location->name] : null,
            ),
        ];
    }

    /**
     * @param  list<array{date: Carbon, health: int}>  $healthObservations
     */
    private static function state(int $historyDays, array $healthObservations): string
    {
        if ($historyDays < self::GATE_DAYS) {
            return 'countdown';
        }

        if ($healthObservations === []) {
            return 'no_health_data';
        }

        return 'ready';
    }

    /**
     * @param  list<array{date: Carbon, health: int}>  $healthObservations
     * @return array<string, mixed>|null
     */
    private static function watering(Plant $plant, array $healthObservations, Carbon $earliest, Carbon $now): ?array
    {
        $waterings = $plant->wateringEvents
            ->map(fn (CareEvent $event): Carbon => $event->occurred_at)
            ->values()
            ->all();

        $amounts = [];
        foreach ($plant->wateringEvents as $event) {
            $amount = $event->watering?->amount_ml;
            if ($amount !== null) {
                $amounts[] = $amount;
            }
        }

        $recommendation = WateringScheduleRecommender::recommend($waterings, $healthObservations, $amounts, $earliest, $now);
        if ($recommendation === null) {
            return null;
        }

        return $recommendation + ['computed_at' => $now->toIso8601String()];
    }

    /**
     * @return list<array{date: Carbon, from: array{id: int, name: string}|null, to: array{id: int, name: string}|null}>
     */
    private static function moves(Plant $plant): array
    {
        return $plant->relocationEvents
            ->map(fn (CareEvent $event): array => [
                'date' => $event->occurred_at,
                'from' => $event->relocation?->fromLocation
                    ? ['id' => $event->relocation->fromLocation->id, 'name' => $event->relocation->fromLocation->name]
                    : null,
                'to' => $event->relocation?->toLocation
                    ? ['id' => $event->relocation->toLocation->id, 'name' => $event->relocation->toLocation->name]
                    : null,
            ])
            ->values()
            ->all();
    }
}
