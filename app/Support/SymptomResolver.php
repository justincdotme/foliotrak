<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Symptom;

/**
 * Merges chosen symptom ids with freetext labels (found-or-created as custom rows)
 * into a single deduplicated id list for attaching to an observation.
 */
final class SymptomResolver
{
    /**
     * @param list<int>    $symptomIds
     * @param list<string> $customLabels
     *
     * @return list<int>
     */
    public static function resolveIds(array $symptomIds, array $customLabels): array
    {
        $custom = array_map(
            static fn (string $label): int => Symptom::findOrCreateCustom($label)->id,
            $customLabels,
        );

        return array_values(array_unique([...$symptomIds, ...$custom]));
    }
}
