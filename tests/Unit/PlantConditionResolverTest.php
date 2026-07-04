<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PlantStatus;
use App\Enums\SymptomCategory;
use App\Models\Symptom;
use App\Support\PlantConditionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlantConditionResolverTest extends TestCase
{
    /**
     * @param  list<SymptomCategory>  $symptomCategories
     * @param  list<string>  $symptomKeys
     */
    #[DataProvider('conditionCases')]
    public function test_resolves_condition_by_first_matching_rule(
        PlantStatus $status,
        ?int $overallHealth,
        array $symptomCategories,
        array $symptomKeys,
        bool $likelyDry,
        string $expectedKey,
        string $expectedLabel,
    ): void {
        $condition = PlantConditionResolver::resolve(
            $status,
            $overallHealth,
            $symptomCategories,
            $symptomKeys,
            $likelyDry,
        );

        $this->assertSame(
            ['key' => $expectedKey, 'label' => $expectedLabel],
            $condition,
        );
    }

    /**
     * @return iterable<string, array{PlantStatus, int|null, list<SymptomCategory>, list<string>, bool, string, string}>
     */
    public static function conditionCases(): iterable
    {
        yield 'dead outranks every other signal' => [PlantStatus::Dead, 5, [SymptomCategory::Pest], [Symptom::KEY_BROWN_TIPS], true, 'dead', 'Did not make it'];
        yield 'pest reads as infested' => [PlantStatus::Active, 4, [SymptomCategory::Pest], [], false, 'infested', 'Infested'];
        yield 'pest outranks disease' => [PlantStatus::Active, 4, [SymptomCategory::Pest, SymptomCategory::Disease], [], false, 'infested', 'Infested'];
        yield 'disease reads as diseased' => [PlantStatus::Active, 4, [SymptomCategory::Disease], [], false, 'diseased', 'Diseased'];
        yield 'overdue watering outranks a healthy reading' => [PlantStatus::Active, 5, [], [], true, 'dry', 'Likely dry'];
        yield 'disease outranks likely dry' => [PlantStatus::Active, 3, [SymptomCategory::Disease], [], true, 'diseased', 'Diseased'];
        yield 'brown tips read as leaf stress' => [PlantStatus::Active, 5, [], [Symptom::KEY_BROWN_TIPS], false, 'burnt', 'Leaf stress'];
        yield 'leaf spots read as leaf stress' => [PlantStatus::Active, 5, [], [Symptom::KEY_LEAF_SPOTS], false, 'burnt', 'Leaf stress'];
        yield 'likely dry outranks leaf stress' => [PlantStatus::Active, 5, [], [Symptom::KEY_BROWN_TIPS], true, 'dry', 'Likely dry'];
        yield 'no observation reads as no reading' => [PlantStatus::Active, null, [], [], false, 'unknown', 'No reading'];
        yield 'null health with a non-triggering symptom is still no reading' => [PlantStatus::Active, null, [SymptomCategory::Leaf], ['leaf_yellowing'], false, 'unknown', 'No reading'];
        yield 'health five reads as healthy' => [PlantStatus::Active, 5, [], [], false, 'healthy', 'Healthy'];
        yield 'health four reads as healthy' => [PlantStatus::Active, 4, [], [], false, 'healthy', 'Healthy'];
        yield 'health three reads as fair' => [PlantStatus::Active, 3, [], [], false, 'fair', 'Fair'];
        yield 'health two reads as struggling' => [PlantStatus::Active, 2, [], [], false, 'struggling', 'Struggling'];
        yield 'health one reads as struggling' => [PlantStatus::Active, 1, [], [], false, 'struggling', 'Struggling'];
    }
}
