<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SymptomCategory;
use App\Models\Plant;
use App\Models\Symptom;

class ProblemFlagger
{
    /**
     * Dashboard problem cards derived from a plant's latest observation: a low
     * health reading, root trouble, and pest or disease signals. Strings stay
     * descriptive, never causal.
     *
     * @return list<array{plant_id: int, common_name: string|null, problem: string, severity: string}>
     */
    public static function flags(Plant $plant): array
    {
        $observation = $plant->latestObservationEvent?->observation;
        if ($observation === null) {
            return [];
        }

        $keys = $observation->symptoms->pluck('key')->all();
        $categories = $observation->symptoms->pluck('category')->all();

        $flags = [];
        $health = $observation->overall_health;

        if ($health !== null && $health <= 2) {
            $flags[] = self::flag($plant, "Low overall health ({$health}/5)", 'alert');
        }
        if (in_array(Symptom::KEY_ROOT_ROT, $keys, true)) {
            $flags[] = self::flag($plant, 'Root rot reported', 'alert');
        }
        if (in_array(Symptom::KEY_ROOT_BOUND, $keys, true)) {
            $flags[] = self::flag($plant, 'Root-bound signs', 'warning');
        }
        if (in_array(SymptomCategory::Pest, $categories, true)) {
            $flags[] = self::flag($plant, 'Pest activity', 'alert');
        }
        if (in_array(SymptomCategory::Disease, $categories, true)) {
            $flags[] = self::flag($plant, 'Disease signs', 'alert');
        }

        return $flags;
    }

    /**
     * @return array{plant_id: int, common_name: string|null, problems: list<array{problem: string, severity: string}>}|null
     */
    public static function forPlant(Plant $plant): ?array
    {
        $flags = self::flags($plant);
        if ($flags === []) {
            return null;
        }

        return [
            'plant_id' => $plant->id,
            'common_name' => $plant->common_name,
            'problems' => array_map(
                static fn (array $flag): array => [
                    'problem' => $flag['problem'],
                    'severity' => $flag['severity'],
                ],
                $flags,
            ),
        ];
    }

    /**
     * @return array{plant_id: int, common_name: string|null, problem: string, severity: string}
     */
    private static function flag(Plant $plant, string $problem, string $severity): array
    {
        return [
            'plant_id' => $plant->id,
            'common_name' => $plant->common_name,
            'problem' => $problem,
            'severity' => $severity,
        ];
    }
}
