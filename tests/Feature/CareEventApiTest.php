<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FertilizerForm;
use App\Models\FertilizingNutrient;
use App\Models\Nutrient;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareEventApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
    }

    private function actAsHousehold(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_logging_a_watering_requires_authentication(): void
    {
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/waterings", ['occurred_at' => now()->toISOString()])
            ->assertUnauthorized();
    }

    public function test_logs_a_watering_on_the_spine_with_its_detail(): void
    {
        $user = $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $response = $this->postJson("/api/plants/{$plant->id}/waterings", [
            'occurred_at' => '2026-06-20T08:30:00Z',
            'amount_ml' => 200,
            'note' => 'Deep soak',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'watering')
            ->assertJsonPath('data.plant_id', $plant->id)
            ->assertJsonPath('data.logged_by_user_id', $user->id)
            ->assertJsonPath('data.note', 'Deep soak')
            ->assertJsonPath('data.watering.amount_ml', 200)
            ->assertJsonMissingPath('data.observation');

        $this->assertDatabaseHas('care_events', ['id' => $response->json('data.id'), 'plant_id' => $plant->id]);
        $this->assertDatabaseHas('watering_details', ['care_event_id' => $response->json('data.id'), 'amount_ml' => 200]);
    }

    public function test_logs_a_liquid_fertilizing_with_npk(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $liquid = FertilizerForm::where('key', 'liquid')->value('id');

        $this->postJson("/api/plants/{$plant->id}/fertilizings", [
            'occurred_at' => '2026-06-18T09:15:00Z',
            'fertilizer_form_id' => $liquid,
            'brand' => 'Dyna-Gro',
            'product' => 'Foliage-Pro',
            'npk_n' => 9,
            'npk_p' => 3,
            'npk_k' => 6,
            'dose_pct' => 50,
            'amount_ml' => 240,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'fertilizing')
            ->assertJsonPath('data.fertilizing.form', 'liquid')
            ->assertJsonPath('data.fertilizing.npk_n', 9)
            ->assertJsonPath('data.fertilizing.dose_pct', 50)
            ->assertJsonPath('data.fertilizing.nutrients', []);
    }

    public function test_logs_an_organic_fertilizing_with_nutrient_components(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $organic = FertilizerForm::where('key', 'organic')->value('id');
        $fish = Nutrient::where('key', 'fish_emulsion')->value('id');
        $kelp = Nutrient::where('key', 'kelp')->value('id');

        $response = $this->postJson("/api/plants/{$plant->id}/fertilizings", [
            'occurred_at' => '2026-06-15T09:15:00Z',
            'fertilizer_form_id' => $organic,
            'brand' => "Neptune's Harvest",
            'dose_pct' => 50,
            'amount_ml' => 300,
            'nutrients' => [
                ['nutrient_id' => $fish, 'note' => '2-3-1'],
                ['nutrient_id' => $kelp],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.fertilizing.form', 'organic')
            ->assertJsonCount(2, 'data.fertilizing.nutrients');

        $nutrients = collect($response->json('data.fertilizing.nutrients'))
            ->keyBy('nutrient_key');
        $this->assertSame('2-3-1', $nutrients['fish_emulsion']['note']);
        $this->assertNull($nutrients['kelp']['note']);

        $this->assertDatabaseHas('fertilizing_nutrients', [
            'care_event_id' => $response->json('data.id'),
            'nutrient_id' => $fish,
            'note' => '2-3-1',
        ]);
    }

    public function test_logs_a_repotting(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/repottings", [
            'occurred_at' => '2026-05-15T11:00:00Z',
            'soil_recipe' => '5 parts bark, 2 parts coir, 1 part perlite',
            'pot_size_value' => 10,
            'pot_size_unit' => 'in',
            'fertilizer_added' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'repotting')
            ->assertJsonPath('data.repotting.pot_size_value', 10)
            ->assertJsonPath('data.repotting.pot_size_unit', 'in')
            ->assertJsonPath('data.repotting.fertilizer_added', true);
    }

    public function test_logs_an_observation_summing_weight_and_attaching_symptoms(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $spiderMites = Symptom::where('key', 'spider_mites')->value('id');

        $response = $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'overall_health' => 4,
            'light_level' => 6,
            'growth_rate' => 'fast',
            'leaf_size_mm' => 96,
            'weight' => ['lb' => 2, 'oz' => 3, 'g' => 5],
            'symptom_ids' => [$spiderMites],
            'custom_symptoms' => ['Sticky residue'],
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'observation')
            ->assertJsonPath('data.observation.overall_health', 4)
            ->assertJsonPath('data.observation.growth_rate', 'fast')
            // lb/oz/g summed to canonical grams server-side.
            ->assertJsonPath('data.observation.weight_grams', 997)
            ->assertJsonPath('data.observation.weight.lb', 2)
            ->assertJsonCount(2, 'data.observation.symptoms');

        $this->assertDatabaseHas('observations', [
            'care_event_id' => $response->json('data.id'),
            'weight_grams' => 997,
        ]);
        $this->assertDatabaseHas('symptoms', ['key' => 'sticky_residue', 'is_custom' => true, 'category' => 'custom']);
    }

    public function test_custom_symptom_variants_collapse_onto_one_reusable_row(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-20T18:00:00Z',
            'custom_symptoms' => ['Sticky residue'],
        ])->assertCreated();

        $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-21T18:00:00Z',
            'custom_symptoms' => ['sticky  residue!'],
        ])->assertCreated();

        $this->assertSame(1, Symptom::where('key', 'sticky_residue')->count());
    }

    public function test_observation_rejects_out_of_range_health(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'overall_health' => 6,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('overall_health');
    }

    public function test_fertilizing_requires_a_form(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/fertilizings", ['occurred_at' => now()->toISOString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('fertilizer_form_id');
    }

    public function test_edits_a_watering_event(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $created = $this->postJson("/api/plants/{$plant->id}/waterings", [
            'occurred_at' => '2026-06-20T08:30:00Z',
            'amount_ml' => 200,
        ])->json('data.id');

        $this->patchJson("/api/care-events/{$created}", ['amount_ml' => 320, 'note' => 'Corrected'])
            ->assertOk()
            ->assertJsonPath('data.watering.amount_ml', 320)
            ->assertJsonPath('data.note', 'Corrected');
    }

    public function test_editing_an_observation_resyncs_its_symptoms(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $mites = Symptom::where('key', 'spider_mites')->value('id');
        $mildew = Symptom::where('key', 'powdery_mildew')->value('id');

        $eventId = $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'symptom_ids' => [$mites],
        ])->json('data.id');

        $this->patchJson("/api/care-events/{$eventId}", ['symptom_ids' => [$mildew]])
            ->assertOk()
            ->assertJsonCount(1, 'data.observation.symptoms')
            ->assertJsonPath('data.observation.symptoms.0.key', 'powdery_mildew');
    }

    public function test_deleting_an_event_removes_its_detail_row(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $eventId = $this->postJson("/api/plants/{$plant->id}/waterings", [
            'occurred_at' => '2026-06-20T08:30:00Z',
            'amount_ml' => 200,
        ])->json('data.id');

        $this->deleteJson("/api/care-events/{$eventId}")->assertNoContent();

        $this->assertDatabaseMissing('care_events', ['id' => $eventId]);
        $this->assertDatabaseMissing('watering_details', ['care_event_id' => $eventId]);
    }

    public function test_a_freetext_symptom_matching_a_seeded_label_reuses_that_row(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        // "Spider mites" slugs to the seeded pest key, so it must attach to that row,
        // not mint a duplicate custom one, or correlation joins split.
        $response = $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'overall_health' => 4,
            'custom_symptoms' => ['Spider mites'],
        ])->assertCreated();

        $response->assertJsonCount(1, 'data.observation.symptoms')
            ->assertJsonPath('data.observation.symptoms.0.key', 'spider_mites')
            ->assertJsonPath('data.observation.symptoms.0.category', 'pest')
            ->assertJsonPath('data.observation.symptoms.0.is_custom', false);

        $this->assertSame(15, Symptom::count());
        $this->getJson("/api/plants/{$plant->id}")->assertJsonPath('data.condition.key', 'infested');
    }

    public function test_editing_a_fertilizing_replaces_its_nutrient_set(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $organic = FertilizerForm::where('key', 'organic')->value('id');
        [$fish, $kelp, $worm] = [
            Nutrient::where('key', 'fish_emulsion')->value('id'),
            Nutrient::where('key', 'kelp')->value('id'),
            Nutrient::where('key', 'worm_castings')->value('id'),
        ];

        $eventId = $this->postJson("/api/plants/{$plant->id}/fertilizings", [
            'occurred_at' => '2026-06-15T09:15:00Z',
            'fertilizer_form_id' => $organic,
            'nutrients' => [['nutrient_id' => $fish], ['nutrient_id' => $kelp]],
        ])->json('data.id');

        $this->patchJson("/api/care-events/{$eventId}", [
            'nutrients' => [['nutrient_id' => $worm, 'note' => 'top dressed']],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.fertilizing.nutrients')
            ->assertJsonPath('data.fertilizing.nutrients.0.nutrient_key', 'worm_castings');

        $this->assertSame(1, FertilizingNutrient::where('care_event_id', $eventId)->count());
    }

    public function test_editing_the_latest_relocation_keeps_the_plant_location_in_sync(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create(['location' => 'south window']);

        $eventId = $this->postJson("/api/plants/{$plant->id}/relocations", ['to_location' => 'east window'])
            ->json('data.id');

        $this->patchJson("/api/care-events/{$eventId}", ['to_location' => 'west window'])
            ->assertOk()
            ->assertJsonPath('data.relocation.to_location', 'west window');

        // The plant's current location must follow its latest move, never drift.
        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location' => 'west window']);
    }

    public function test_a_zero_weight_is_stored_as_not_recorded(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'overall_health' => 4,
            'weight' => ['lb' => 0, 'oz' => 0, 'g' => 0],
        ])
            ->assertCreated()
            ->assertJsonPath('data.observation.weight_grams', null)
            ->assertJsonPath('data.observation.weight', null);
    }

    public function test_observation_rejects_an_oversized_weight_component(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'weight' => ['lb' => 100000, 'oz' => 0, 'g' => 0],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('weight.lb');
    }

    public function test_null_arrays_are_accepted_as_empty(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $liquid = FertilizerForm::where('key', 'liquid')->value('id');

        // Explicit null for the optional collections must not 500 the create path.
        $this->postJson("/api/plants/{$plant->id}/fertilizings", [
            'occurred_at' => '2026-06-15T09:15:00Z',
            'fertilizer_form_id' => $liquid,
            'nutrients' => null,
        ])->assertCreated()->assertJsonPath('data.fertilizing.nutrients', []);

        $this->postJson("/api/plants/{$plant->id}/observations", [
            'occurred_at' => '2026-06-22T18:00:00Z',
            'symptom_ids' => null,
            'custom_symptoms' => null,
        ])->assertCreated()->assertJsonPath('data.observation.symptoms', []);
    }
}
