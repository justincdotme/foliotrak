<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CareEventType;
use App\Models\FertilizerForm;
use App\Models\Nutrient;
use App\Models\Symptom;
use Illuminate\Database\Seeder;

/**
 * Seeds the care-logging vocabularies. The symptom and nutrient sets mirror the
 * prototype's chips (the design source of truth), so the lookup endpoints serve
 * exactly what the Log Care forms render and the condition resolver keys off.
 */
class CareLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCareEventTypes();
        $this->seedFertilizerForms();
        $this->seedNutrients();
        $this->seedSymptoms();
    }

    private function seedCareEventTypes(): void
    {
        $types = [
            ['key' => 'watering', 'label' => 'Watering'],
            ['key' => 'fertilizing', 'label' => 'Fertilizing'],
            ['key' => 'repotting', 'label' => 'Repotting'],
            ['key' => 'observation', 'label' => 'Observation'],
            ['key' => 'relocation', 'label' => 'Relocation'],
        ];

        foreach ($types as $order => $type) {
            CareEventType::firstOrCreate(
                ['key' => $type['key']],
                ['label' => $type['label'], 'sort_order' => $order + 1],
            );
        }
    }

    private function seedFertilizerForms(): void
    {
        $forms = [
            ['key' => 'liquid', 'label' => 'Liquid'],
            ['key' => 'powdered', 'label' => 'Powdered'],
            ['key' => 'granular', 'label' => 'Granular'],
            ['key' => 'organic', 'label' => 'Organic'],
            ['key' => 'food', 'label' => 'Plant food'],
            ['key' => 'other', 'label' => 'Other'],
        ];

        foreach ($forms as $order => $form) {
            FertilizerForm::firstOrCreate(
                ['key' => $form['key']],
                ['label' => $form['label'], 'sort_order' => $order + 1],
            );
        }
    }

    private function seedNutrients(): void
    {
        $nutrients = [
            ['key' => 'kelp', 'label' => 'Kelp / seaweed'],
            ['key' => 'fish_emulsion', 'label' => 'Fish emulsion'],
            ['key' => 'worm_castings', 'label' => 'Worm castings'],
            ['key' => 'humic_acid', 'label' => 'Humic acid'],
        ];

        foreach ($nutrients as $order => $nutrient) {
            Nutrient::firstOrCreate(
                ['key' => $nutrient['key']],
                ['label' => $nutrient['label'], 'symbol' => null, 'sort_order' => $order + 1],
            );
        }
    }

    private function seedSymptoms(): void
    {
        $symptoms = [
            ['category' => 'leaf', 'key' => 'yellow_leaf', 'label' => 'Yellowing leaves'],
            ['category' => 'leaf', 'key' => 'brown_tips', 'label' => 'Brown leaf tips'],
            ['category' => 'leaf', 'key' => 'leaf_drop', 'label' => 'Leaf drop'],
            ['category' => 'leaf', 'key' => 'leaf_curl', 'label' => 'Leaf curl'],
            ['category' => 'leaf', 'key' => 'leaf_spots', 'label' => 'Leaf spots'],
            ['category' => 'stem', 'key' => 'soft_stem', 'label' => 'Soft stem'],
            ['category' => 'stem', 'key' => 'leggy', 'label' => 'Leggy growth'],
            ['category' => 'root', 'key' => 'root_bound', 'label' => 'Root-bound'],
            ['category' => 'root', 'key' => 'root_rot', 'label' => 'Root rot'],
            ['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites'],
            ['category' => 'pest', 'key' => 'fungus_gnats', 'label' => 'Fungus gnats'],
            ['category' => 'pest', 'key' => 'mealybugs', 'label' => 'Mealybugs'],
            ['category' => 'disease', 'key' => 'powdery_mildew', 'label' => 'Powdery mildew'],
            ['category' => 'general', 'key' => 'wilting', 'label' => 'Wilting'],
            ['category' => 'general', 'key' => 'drooping', 'label' => 'Drooping'],
        ];

        foreach ($symptoms as $order => $symptom) {
            Symptom::firstOrCreate(
                ['key' => $symptom['key']],
                [
                    'category' => $symptom['category'],
                    'label' => $symptom['label'],
                    'sort_order' => $order + 1,
                    'is_custom' => false,
                ],
            );
        }
    }
}
