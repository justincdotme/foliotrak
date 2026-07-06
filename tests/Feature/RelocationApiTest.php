<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelocationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    /** @return void */
    public function test_logging_a_relocation_mirrors_the_location_and_records_the_move(): void
    {
        $south = Location::factory()->create(['name' => 'south window']);
        $east  = Location::factory()->create(['name' => 'east window']);
        $plant = Plant::factory()->create(['location_id' => $south->id]);

        $this->postJson("/api/plants/{$plant->id}/care-events", [
            'type'           => 'relocation',
            'to_location_id' => $east->id,
            'occurred_at'    => '2026-06-20T12:00:00Z',
            'note'           => 'Winter light',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'relocation')
            ->assertJsonPath('data.relocation.from_location.name', 'south window')
            ->assertJsonPath('data.relocation.to_location.name', 'east window')
            ->assertJsonPath('data.note', 'Winter light');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location_id' => $east->id]);
        $this->assertDatabaseHas('relocation_details', [
            'from_location_id' => $south->id,
            'to_location_id'   => $east->id,
        ]);
    }

    /** @return void */
    public function test_relocating_to_the_current_location_is_a_no_op(): void
    {
        $south = Location::factory()->create(['name' => 'south window']);
        $plant = Plant::factory()->create(['location_id' => $south->id]);

        $this->postJson("/api/plants/{$plant->id}/care-events", ['type' => 'relocation', 'to_location_id' => $south->id])
            ->assertNoContent();

        $this->assertDatabaseCount('care_events', 0);
        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location_id' => $south->id]);
    }

    /** @return void */
    public function test_changing_location_via_patch_logs_exactly_one_relocation(): void
    {
        $south   = Location::factory()->create(['name' => 'south window']);
        $kitchen = Location::factory()->create(['name' => 'kitchen sill']);
        $plant   = Plant::factory()->create(['location_id' => $south->id]);

        $this->patchJson("/api/plants/{$plant->id}", ['location_id' => $kitchen->id])
            ->assertOk()
            ->assertJsonPath('data.location.name', 'kitchen sill');

        $this->assertDatabaseCount('care_events', 1);
        $event = CareEvent::firstOrFail();
        $this->assertDatabaseHas('relocation_details', [
            'care_event_id'    => $event->id,
            'from_location_id' => $south->id,
            'to_location_id'   => $kitchen->id,
        ]);
    }

    /** @return void */
    public function test_patching_an_unchanged_location_logs_nothing(): void
    {
        $south = Location::factory()->create(['name' => 'south window']);
        $plant = Plant::factory()->create(['location_id' => $south->id]);

        $this->patchJson("/api/plants/{$plant->id}", ['location_id' => $south->id, 'notes' => 'no move'])
            ->assertOk();

        $this->assertDatabaseCount('care_events', 0);
    }

    /** @return void */
    public function test_patching_other_fields_logs_no_relocation(): void
    {
        $south = Location::factory()->create(['name' => 'south window']);
        $plant = Plant::factory()->create(['location_id' => $south->id]);

        $this->patchJson("/api/plants/{$plant->id}", ['notes' => 'just a note'])->assertOk();

        $this->assertDatabaseCount('care_events', 0);
    }
}
