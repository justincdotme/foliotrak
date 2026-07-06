<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlantStatus;
use App\Enums\SymptomCategory;
use App\Models\Symptom;

final class PlantConditionResolver
{
    /**
     * @param PlantStatus           $status
     * @param integer|null          $overallHealth
     * @param list<SymptomCategory> $symptomCategories Categories on the latest observation.
     * @param list<string>          $symptomKeys       Symptom keys on the latest observation.
     * @param boolean               $likelyDry         Watering overdue beyond max(2, interval * 0.4) days.
     *
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

        if (in_array(SymptomCategory::Pest, $symptomCategories, true)) {
            return ['key' => 'infested', 'label' => 'Infested'];
        }

        if (in_array(SymptomCategory::Disease, $symptomCategories, true)) {
            return ['key' => 'diseased', 'label' => 'Diseased'];
        }

        if ($likelyDry) {
            return ['key' => 'dry', 'label' => 'Likely dry'];
        }

        if (in_array(Symptom::KEY_BROWN_TIPS, $symptomKeys, true) || in_array(Symptom::KEY_LEAF_SPOTS, $symptomKeys, true)) {
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
