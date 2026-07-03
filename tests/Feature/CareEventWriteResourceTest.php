<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FertilizerForm;
use App\Models\Location;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers cross-cutting behavior of the unified care-event write endpoint that the
 * per-type tests in CareEventApiTest and RelocationApiTest don't isolate: type
 * dispatch/validation, relocation through the same endpoint, create/edit parity
 * for a shared field, and bound consistency between create and update rules.
 */
class CareEventWriteResourceTest extends TestCase
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

    public function test_missing_type_is_rejected(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/care-events", ['occurred_at' => now()->toISOString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('type');
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => 'sprinkling',
            'occurred_at' => now()->toISOString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('type');
    }

    public function test_each_type_dispatches_to_its_own_detail_table(): void
    {
        $this->actAsHousehold();
        $liquid = FertilizerForm::where('key', 'liquid')->value('id');

        $cases = [
            'watering' => ['payload' => [], 'table' => 'watering_details', 'sibling' => 'fertilizing_details'],
            'fertilizing' => ['payload' => ['fertilizer_form_id' => $liquid], 'table' => 'fertilizing_details', 'sibling' => 'watering_details'],
            'repotting' => ['payload' => [], 'table' => 'repotting_details', 'sibling' => 'observations'],
            'observation' => ['payload' => [], 'table' => 'observations', 'sibling' => 'repotting_details'],
            'relocation' => ['payload' => ['to_location_id' => Location::factory()->create()->id], 'table' => 'relocation_details', 'sibling' => 'watering_details'],
        ];

        foreach ($cases as $type => $case) {
            $plant = Plant::factory()->create();

            $response = $this->postJson("/api/plants/{$plant->id}/care-events", [
                'type' => $type,
                'occurred_at' => now()->toISOString(),
                ...$case['payload'],
            ])->assertCreated();

            $eventId = $response->json('data.id');

            $this->assertDatabaseHas($case['table'], ['care_event_id' => $eventId]);
            $this->assertDatabaseMissing($case['sibling'], ['care_event_id' => $eventId]);
        }
    }

    public function test_relocation_via_the_unified_endpoint_mirrors_the_plant_location(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $office = Location::factory()->create(['name' => 'office shelf']);

        $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => 'relocation',
            'to_location_id' => $office->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'relocation')
            ->assertJsonPath('data.relocation.to_location.name', 'office shelf');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location_id' => $office->id]);
    }

    public function test_relocation_to_the_current_location_is_a_no_op_via_the_unified_endpoint(): void
    {
        $this->actAsHousehold();
        $current = Location::factory()->create(['name' => 'south window']);
        $plant = Plant::factory()->create(['location_id' => $current->id]);

        $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => 'relocation',
            'to_location_id' => $current->id,
        ])->assertNoContent();

        $this->assertDatabaseCount('care_events', 0);
    }

    public function test_observation_light_level_is_settable_identically_on_create_and_update(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $created = $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => 'observation',
            'occurred_at' => now()->toISOString(),
            'light_level' => 3,
        ])->assertCreated();

        $eventId = $created->json('data.id');
        $created->assertJsonPath('data.observation.light_level', 3);
        $this->assertDatabaseHas('observations', ['care_event_id' => $eventId, 'light_level' => 3]);

        $this->patchJson("/api/care-events/{$eventId}", ['light_level' => 8])
            ->assertOk()
            ->assertJsonPath('data.observation.light_level', 8);

        $this->assertDatabaseHas('observations', ['care_event_id' => $eventId, 'light_level' => 8]);
    }

    public function test_update_rejects_an_out_of_range_bound_matching_create(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $eventId = $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => 'watering',
            'occurred_at' => now()->toISOString(),
            'amount_ml' => 200,
        ])->json('data.id');

        $this->patchJson("/api/care-events/{$eventId}", ['amount_ml' => 4294967296])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('amount_ml');
    }

    public function test_non_scalar_type_is_rejected_with_validation_error(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => ['watering'],
            'occurred_at' => now()->toISOString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('type');
    }

    public function test_relocation_accepts_to_location_id_as_a_numeric_string(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $office = Location::factory()->create(['name' => 'office shelf']);

        $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type' => 'relocation',
            'to_location_id' => (string) $office->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'relocation')
            ->assertJsonPath('data.relocation.to_location.name', 'office shelf');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location_id' => $office->id]);
    }
}
