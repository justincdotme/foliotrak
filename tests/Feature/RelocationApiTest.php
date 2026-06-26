<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_logging_a_relocation_mirrors_the_location_and_records_the_move(): void
    {
        $plant = Plant::factory()->create(['location' => 'south window']);

        $this->postJson("/api/plants/{$plant->id}/relocations", [
            'to_location' => 'east window',
            'occurred_at' => '2026-06-20T12:00:00Z',
            'note' => 'Winter light',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'relocation')
            ->assertJsonPath('data.relocation.from_location', 'south window')
            ->assertJsonPath('data.relocation.to_location', 'east window')
            ->assertJsonPath('data.note', 'Winter light');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location' => 'east window']);
        $this->assertDatabaseHas('relocation_details', [
            'from_location' => 'south window',
            'to_location' => 'east window',
        ]);
    }

    public function test_relocating_to_the_current_location_is_a_no_op(): void
    {
        $plant = Plant::factory()->create(['location' => 'south window']);

        $this->postJson("/api/plants/{$plant->id}/relocations", ['to_location' => 'south window'])
            ->assertNoContent();

        $this->assertDatabaseCount('care_events', 0);
        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location' => 'south window']);
    }

    public function test_changing_location_via_patch_logs_exactly_one_relocation(): void
    {
        $plant = Plant::factory()->create(['location' => 'south window']);

        $this->patchJson("/api/plants/{$plant->id}", ['location' => 'kitchen sill'])
            ->assertOk()
            ->assertJsonPath('data.location', 'kitchen sill');

        $this->assertDatabaseCount('care_events', 1);
        $event = CareEvent::firstOrFail();
        $this->assertDatabaseHas('relocation_details', [
            'care_event_id' => $event->id,
            'from_location' => 'south window',
            'to_location' => 'kitchen sill',
        ]);
    }

    public function test_patching_an_unchanged_location_logs_nothing(): void
    {
        $plant = Plant::factory()->create(['location' => 'south window']);

        $this->patchJson("/api/plants/{$plant->id}", ['location' => 'south window', 'notes' => 'no move'])
            ->assertOk();

        $this->assertDatabaseCount('care_events', 0);
    }

    public function test_patching_other_fields_logs_no_relocation(): void
    {
        $plant = Plant::factory()->create(['location' => 'south window']);

        $this->patchJson("/api/plants/{$plant->id}", ['notes' => 'just a note'])->assertOk();

        $this->assertDatabaseCount('care_events', 0);
    }
}
