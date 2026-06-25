<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlantStatus;

/**
 * The single source for a plant's at-a-glance condition (decision D24), so every
 * surface that shows it agrees. Phase 1a feeds it status only; the observation and
 * watering-due inputs are wired in once the care spine lands in Phase 2a.
 */
final class PlantConditionResolver
{
    /**
     * @param  list<string>  $symptomCategories  categories on the latest observation
     * @param  list<string>  $symptomKeys  symptom keys on the latest observation
     * @param  bool  $likelyDry  watering overdue beyond max(2, interval * 0.4) days
     * @return array{key: string, label: string}
     */
    public static function resolve(
        PlantStatus $status,
        ?int $overallHealth = null,
        array $symptomCategories = [],
        array $symptomKeys = [],
        bool $likelyDry = false,
    ): array {
        if ($status === PlantStatus::Dead) {
            return ['key' => 'dead', 'label' => 'Did not make it'];
        }

        if (in_array('pest', $symptomCategories, true)) {
            return ['key' => 'infested', 'label' => 'Infested'];
        }

        if (in_array('disease', $symptomCategories, true)) {
            return ['key' => 'diseased', 'label' => 'Diseased'];
        }

        if ($likelyDry) {
            return ['key' => 'dry', 'label' => 'Likely dry'];
        }

        if (in_array('brown_tips', $symptomKeys, true) || in_array('leaf_spots', $symptomKeys, true)) {
            return ['key' => 'burnt', 'label' => 'Leaf stress'];
        }

        if ($overallHealth === null) {
            return ['key' => 'unknown', 'label' => 'No reading'];
        }

        if ($overallHealth >= 4) {
            return ['key' => 'healthy', 'label' => 'Healthy'];
        }

        if ($overallHealth === 3) {
            return ['key' => 'fair', 'label' => 'Fair'];
        }

        return ['key' => 'struggling', 'label' => 'Struggling'];
    }
}
