<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SymptomCategory;
use App\Models\CareEventType;
use App\Models\Equipment;
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
    /** @return void */
    public function run(): void
    {
        $this->seedCareEventTypes();
        $this->seedFertilizerForms();
        $this->seedNutrients();
        $this->seedSymptoms();
        $this->seedEquipment();
    }

    /** @return void */
    private function seedCareEventTypes(): void
    {
        $types = [
            ['key' => 'watering', 'label' => 'Watering'],
            ['key' => 'fertilizing', 'label' => 'Fertilizing'],
            ['key' => 'repotting', 'label' => 'Repotting'],
            ['key' => 'observation', 'label' => 'Observation'],
            ['key' => 'relocation', 'label' => 'Relocation'],
            ['key' => 'equipment', 'label' => 'Equipment'],
        ];

        foreach ($types as $order => $type) {
            CareEventType::firstOrCreate(
                ['key' => $type['key']],
                ['label' => $type['label'], 'sort_order' => $order + 1],
            );
        }
    }

    /** @return void */
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

    /** @return void */
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

    /** @return void */
    private function seedSymptoms(): void
    {
        $symptoms = [
            ['category' => SymptomCategory::Leaf, 'key' => 'yellow_leaf', 'label' => 'Yellowing leaves'],
            ['category' => SymptomCategory::Leaf, 'key' => Symptom::KEY_BROWN_TIPS, 'label' => 'Brown leaf tips'],
            ['category' => SymptomCategory::Leaf, 'key' => 'leaf_drop', 'label' => 'Leaf drop'],
            ['category' => SymptomCategory::Leaf, 'key' => 'leaf_curl', 'label' => 'Leaf curl'],
            ['category' => SymptomCategory::Leaf, 'key' => Symptom::KEY_LEAF_SPOTS, 'label' => 'Leaf spots'],
            ['category' => SymptomCategory::Stem, 'key' => 'soft_stem', 'label' => 'Soft stem'],
            ['category' => SymptomCategory::Stem, 'key' => 'leggy', 'label' => 'Leggy growth'],
            ['category' => SymptomCategory::Root, 'key' => Symptom::KEY_ROOT_BOUND, 'label' => 'Root-bound'],
            ['category' => SymptomCategory::Root, 'key' => Symptom::KEY_ROOT_ROT, 'label' => 'Root rot'],
            ['category' => SymptomCategory::Pest, 'key' => 'spider_mites', 'label' => 'Spider mites'],
            ['category' => SymptomCategory::Pest, 'key' => 'fungus_gnats', 'label' => 'Fungus gnats'],
            ['category' => SymptomCategory::Pest, 'key' => 'mealybugs', 'label' => 'Mealybugs'],
            ['category' => SymptomCategory::Disease, 'key' => 'powdery_mildew', 'label' => 'Powdery mildew'],
            ['category' => SymptomCategory::General, 'key' => 'wilting', 'label' => 'Wilting'],
            ['category' => SymptomCategory::General, 'key' => 'drooping', 'label' => 'Drooping'],
        ];

        foreach ($symptoms as $order => $symptom) {
            Symptom::firstOrCreate(
                ['key' => $symptom['key']],
                [
                    'category'   => $symptom['category'],
                    'label'      => $symptom['label'],
                    'sort_order' => $order + 1,
                    'is_custom'  => false,
                ],
            );
        }
    }

    /** @return void */
    private function seedEquipment(): void
    {
        $items = [
            ['key' => 'humidifier', 'label' => 'Humidifier'],
            ['key' => 'dehumidifier', 'label' => 'Dehumidifier'],
            ['key' => 'grow_light', 'label' => 'Grow light'],
            ['key' => 'heat_mat', 'label' => 'Heat mat'],
            ['key' => 'fan', 'label' => 'Fan'],
        ];

        foreach ($items as $order => $item) {
            Equipment::firstOrCreate(
                ['key' => $item['key']],
                ['label' => $item['label'], 'sort_order' => $order + 1],
            );
        }
    }
}
