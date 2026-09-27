<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlantStatus;
use App\Enums\SensorType;
use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Location;
use App\Models\Photo;
use App\Models\Plant;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        // A location change logs a relocation, which needs the care-event types.
        $this->seed(CareLookupSeeder::class);
    }

    /** @return void */
    public function test_listing_plants_requires_authentication(): void
    {
        $this->getJson('/api/plants')->assertUnauthorized();
    }

    /** @return void */
    public function test_creates_a_plant_and_returns_the_contract_shape(): void
    {
        $this->actAsHousehold();
        $south = Location::factory()->create(['name' => 'south window']);

        $response = $this->postJson('/api/plants', [
            'common_name'                        => 'Swiss cheese plant',
            'scientific_name'                    => 'Monstera deliciosa',
            'gbif_key'                           => '2868125',
            'location_id'                        => $south->id,
            'acquired_on'                        => '2026-01-15',
            'notes'                              => 'Repotted on arrival.',
            'watering_interval_days_override'    => 7,
            'fertilizing_interval_days_override' => 30,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.common_name', 'Swiss cheese plant')
            ->assertJsonPath('data.scientific_name', 'Monstera deliciosa')
            ->assertJsonPath('data.gbif_key', '2868125')
            ->assertJsonPath('data.location.name', 'south window')
            ->assertJsonPath('data.acquired_on', '2026-01-15')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.watering_interval_days_override', 7)
            ->assertJsonPath('data.fertilizing_interval_days_override', 30)
            ->assertJsonPath('data.cover_photo_id', null)
            ->assertJsonPath('data.condition.key', 'unknown')
            ->assertJsonPath('data.condition.label', 'No reading')
            ->assertJsonPath('data.tags', []);

        $this->assertDatabaseHas('plants', [
            'common_name'     => 'Swiss cheese plant',
            'scientific_name' => 'Monstera deliciosa',
            'status'          => 'active',
        ]);
    }

    /** @return void */
    public function test_defaults_status_to_active_when_omitted(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/plants', ['common_name' => 'Pothos'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');
    }

    /** @return void */
    public function test_lists_plants_with_their_derived_condition(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create(['common_name' => 'Living one', 'status' => PlantStatus::Active]);
        Plant::factory()->create(['common_name' => 'Resting one', 'status' => PlantStatus::Archived]);
        Plant::factory()->create(['common_name' => 'Lost one', 'status' => PlantStatus::Dead]);

        $response = $this->getJson('/api/plants')->assertOk();

        $response->assertJsonCount(3, 'data');
        $conditions = collect($response->json('data'))
            ->mapWithKeys(fn (array $plant): array => [$plant['common_name'] => $plant['condition']['key']]);

        // Status is the only signal so far, so only Dead diverges from "no reading".
        $this->assertSame('unknown', $conditions['Living one']);
        $this->assertSame('unknown', $conditions['Resting one']);
        $this->assertSame('dead', $conditions['Lost one']);
    }

    /** @return void */
    public function test_shows_a_single_plant(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create(['common_name' => 'Fiddle leaf fig']);

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $plant->id)
            ->assertJsonPath('data.common_name', 'Fiddle leaf fig')
            ->assertJsonPath('data.condition.key', 'unknown');
    }

    /** @return void */
    public function test_updates_plant_attributes_including_location(): void
    {
        $this->actAsHousehold();
        $south = Location::factory()->create(['name' => 'south window']);
        $east  = Location::factory()->create(['name' => 'east window']);
        $plant = Plant::factory()->create(['location_id' => $south->id, 'status' => PlantStatus::Active]);

        $this->patchJson("/api/plants/{$plant->id}", [
            'location_id'                     => $east->id,
            'status'                          => 'archived',
            'notes'                           => 'Moved for winter light.',
            'watering_interval_days_override' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.location.name', 'east window')
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.watering_interval_days_override', 10);

        $this->assertDatabaseHas('plants', [
            'id'          => $plant->id,
            'location_id' => $east->id,
            'status'      => 'archived',
        ]);
    }

    /** @return void */
    public function test_relocates_when_location_id_is_sent_as_a_string(): void
    {
        $this->actAsHousehold();
        $south = Location::factory()->create(['name' => 'south window']);
        $east  = Location::factory()->create(['name' => 'east window']);
        $plant = Plant::factory()->create(['location_id' => $south->id]);

        // A form-encoded PATCH, or any client that stringifies ids, sends location_id
        // as a string. The SPA sends a JSON int, so patchJson-with-int hid this path.
        $this->patchJson("/api/plants/{$plant->id}", [
            'location_id' => (string) $east->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.location.name', 'east window');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'location_id' => $east->id]);
    }

    /** @return void */
    public function test_rejects_an_invalid_status(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->patchJson("/api/plants/{$plant->id}", ['status' => 'thriving'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('status');
    }

    /** @return void */
    public function test_deleting_a_plant_soft_deletes_it(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->deleteJson("/api/plants/{$plant->id}")->assertNoContent();

        $this->assertSoftDeleted('plants', ['id' => $plant->id]);
        $this->getJson('/api/plants')->assertOk()->assertJsonCount(0, 'data');
    }

    /** @return void */
    public function test_attaches_tags_when_creating_a_plant(): void
    {
        $this->actAsHousehold();
        $pothos  = Tag::factory()->create(['name' => 'Pothos']);
        $kitchen = Tag::factory()->create(['name' => 'Kitchen']);

        $response = $this->postJson('/api/plants', [
            'common_name' => 'Golden pothos',
            'tag_ids'     => [$pothos->id, $kitchen->id],
        ])->assertCreated();

        $names = collect($response->json('data.tags'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Pothos', 'Kitchen'], $names);
        $this->assertDatabaseHas('plant_tag', ['plant_id' => $response->json('data.id'), 'tag_id' => $pothos->id]);
    }

    /** @return void */
    public function test_syncs_tags_on_update_and_leaves_them_alone_when_omitted(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $old   = Tag::factory()->create(['name' => 'Old']);
        $new   = Tag::factory()->create(['name' => 'New']);
        $plant->tags()->attach($old);

        // Omitting tag_ids must not wipe the existing tags.
        $this->patchJson("/api/plants/{$plant->id}", ['notes' => 'untouched tags'])
            ->assertOk()
            ->assertJsonPath('data.tags.0.name', 'Old');

        // Sending tag_ids replaces the set.
        $this->patchJson("/api/plants/{$plant->id}", ['tag_ids' => [$new->id]])
            ->assertOk()
            ->assertJsonPath('data.tags.0.name', 'New')
            ->assertJsonCount(1, 'data.tags');
    }

    /** @return void */
    public function test_shows_sensor_location_and_syncs_sensor_ids_on_update(): void
    {
        $this->actAsHousehold();
        $plant  = Plant::factory()->create();
        $sensor = Sensor::create([
            'mac'      => 'AA:BB:CC:DD:EE:01',
            'name'     => 'Desk sensor',
            'color'    => 'var(--series-1)',
            'location' => 'Living room',
            'type'     => 'hygrometer',
        ]);

        $this->patchJson("/api/plants/{$plant->id}", ['sensor_ids' => [$sensor->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.sensors')
            ->assertJsonPath('data.sensors.0.name', 'Desk sensor')
            ->assertJsonPath('data.sensors.0.location', 'Living room');
    }

    /** @return void */
    public function test_filters_plants_by_tag(): void
    {
        $this->actAsHousehold();
        $kitchen = Tag::factory()->create(['name' => 'Kitchen']);
        $tagged  = Plant::factory()->create(['common_name' => 'On the sill']);
        $tagged->tags()->attach($kitchen);
        Plant::factory()->create(['common_name' => 'In the office']);

        $this->getJson("/api/plants?tag={$kitchen->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.common_name', 'On the sill');
    }

    /** @return void */
    public function test_sorts_plants_by_name_asc(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create(['common_name' => 'Basil']);
        Plant::factory()->create(['common_name' => 'Aloe']);
        Plant::factory()->create(['common_name' => 'Cactus']);

        $this->getJson('/api/plants?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Aloe')
            ->assertJsonPath('data.1.common_name', 'Basil')
            ->assertJsonPath('data.2.common_name', 'Cactus');
    }

    /** @return void */
    public function test_sorts_plants_by_name_desc(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create(['common_name' => 'Basil']);
        Plant::factory()->create(['common_name' => 'Aloe']);
        Plant::factory()->create(['common_name' => 'Cactus']);

        $this->getJson('/api/plants?sort=name&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Cactus')
            ->assertJsonPath('data.1.common_name', 'Basil')
            ->assertJsonPath('data.2.common_name', 'Aloe');
    }

    /** @return void */
    public function test_sorts_plants_by_last_watered_asc(): void
    {
        $this->actAsHousehold();
        $wateringType = CareEventType::where('key', 'watering')->first();

        // Ids intentionally don't correlate with occurred_at, so this only passes
        // if the last_watered sort is doing the ordering rather than the id tiebreaker.
        $middle   = Plant::factory()->create(['common_name' => 'Middle']);
        $latest   = Plant::factory()->create(['common_name' => 'Latest']);
        $earliest = Plant::factory()->create(['common_name' => 'Earliest']);

        CareEvent::factory()->create([
            'plant_id'           => $earliest->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-01',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $middle->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-15',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $latest->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-30',
        ]);

        $this->getJson('/api/plants?sort=last_watered&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Earliest')
            ->assertJsonPath('data.1.common_name', 'Middle')
            ->assertJsonPath('data.2.common_name', 'Latest');
    }

    /** @return void */
    public function test_sorts_plants_by_last_watered_desc(): void
    {
        $this->actAsHousehold();
        $wateringType = CareEventType::where('key', 'watering')->first();

        // Ids intentionally don't correlate with occurred_at, so this only passes
        // if the last_watered sort is doing the ordering rather than the id tiebreaker.
        $middle   = Plant::factory()->create(['common_name' => 'Middle']);
        $latest   = Plant::factory()->create(['common_name' => 'Latest']);
        $earliest = Plant::factory()->create(['common_name' => 'Earliest']);

        CareEvent::factory()->create([
            'plant_id'           => $earliest->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-01',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $middle->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-15',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $latest->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-30',
        ]);

        $this->getJson('/api/plants?sort=last_watered&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Latest')
            ->assertJsonPath('data.1.common_name', 'Middle')
            ->assertJsonPath('data.2.common_name', 'Earliest');
    }

    /** @return void */
    public function test_default_sort_is_last_watered_desc(): void
    {
        $this->actAsHousehold();
        $wateringType = CareEventType::where('key', 'watering')->first();

        // Ids intentionally don't correlate with occurred_at, so this only passes
        // if the last_watered sort is doing the ordering rather than the id tiebreaker.
        $middle   = Plant::factory()->create(['common_name' => 'Middle']);
        $latest   = Plant::factory()->create(['common_name' => 'Latest']);
        $earliest = Plant::factory()->create(['common_name' => 'Earliest']);

        CareEvent::factory()->create([
            'plant_id'           => $earliest->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-01',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $middle->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-15',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $latest->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-30',
        ]);

        $this->getJson('/api/plants')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Latest')
            ->assertJsonPath('data.1.common_name', 'Middle')
            ->assertJsonPath('data.2.common_name', 'Earliest');
    }

    /** @return void */
    public function test_never_watered_plants_sort_last_in_last_watered_desc(): void
    {
        $this->actAsHousehold();
        $wateringType = CareEventType::where('key', 'watering')->first();

        $watered1 = Plant::factory()->create(['common_name' => 'Watered One']);
        $watered2 = Plant::factory()->create(['common_name' => 'Watered Two']);
        Plant::factory()->create(['common_name' => 'Never Watered']);

        CareEvent::factory()->create([
            'plant_id'           => $watered1->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-01',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $watered2->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-15',
        ]);

        $this->getJson('/api/plants?sort=last_watered&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.2.common_name', 'Never Watered');
    }

    /** @return void */
    public function test_never_watered_plants_sort_first_in_last_watered_asc(): void
    {
        $this->actAsHousehold();
        $wateringType = CareEventType::where('key', 'watering')->first();

        $watered1 = Plant::factory()->create(['common_name' => 'Watered One']);
        $watered2 = Plant::factory()->create(['common_name' => 'Watered Two']);
        Plant::factory()->create(['common_name' => 'Never Watered']);

        CareEvent::factory()->create([
            'plant_id'           => $watered1->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-01',
        ]);
        CareEvent::factory()->create([
            'plant_id'           => $watered2->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => '2024-06-15',
        ]);

        $this->getJson('/api/plants?sort=last_watered&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Never Watered');
    }

    /** @return void */
    public function test_rejects_invalid_sort_value(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/plants?sort=invalid')
            ->assertStatus(422);
    }

    /** @return void */
    public function test_rejects_invalid_direction_value(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/plants?direction=invalid')
            ->assertStatus(422);
    }

    /** @return void */
    public function test_sets_an_existing_photo_as_cover_via_patch(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $photo = Photo::factory()->for($plant)->create();

        $this->patchJson("/api/plants/{$plant->id}", ['cover_photo_id' => $photo->id])
            ->assertOk()
            ->assertJsonPath('data.cover_photo_id', $photo->id);
    }

    /** @return void */
    public function test_rejects_a_cover_photo_belonging_to_another_plant(): void
    {
        $this->actAsHousehold();
        $plant        = Plant::factory()->create();
        $foreignPhoto = Photo::factory()->create(); // different plant

        $this->patchJson("/api/plants/{$plant->id}", ['cover_photo_id' => $foreignPhoto->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('cover_photo_id');
    }

    /** @return void */
    public function test_clears_cover_photo_with_null(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $photo = Photo::factory()->for($plant)->create();
        $plant->update(['cover_photo_id' => $photo->id]);

        $this->patchJson("/api/plants/{$plant->id}", ['cover_photo_id' => null])
            ->assertOk()
            ->assertJsonPath('data.cover_photo_id', null);
    }

    /** @return void */
    public function test_embeds_the_cover_photo_so_cards_can_render_a_thumbnail(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $photo = Photo::factory()->for($plant)->create(['path' => 'cover-hash.jpg']);
        $plant->update(['cover_photo_id' => $photo->id]);

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_photo.id', $photo->id)
            ->assertJsonPath('data.cover_photo.path', 'cover-hash.jpg');
    }

    /** @return void */
    public function test_cover_photo_is_null_when_the_plant_has_none(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create(['cover_photo_id' => null]);

        $this->getJson('/api/plants')
            ->assertOk()
            ->assertJsonPath('data.0.cover_photo', null);
    }

    /** @return void */
    public function test_store_accepts_watering_schedule_start_date(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/plants', [
            'common_name'                  => 'Fern',
            'watering_schedule_start_date' => '2026-06-29',
        ])
            ->assertCreated()
            ->assertJsonPath('data.watering_schedule_start_date', '2026-06-29');
    }

    /** @return void */
    public function test_update_accepts_watering_schedule_start_date(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->patchJson("/api/plants/{$plant->id}", [
            'watering_schedule_start_date' => '2026-07-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.watering_schedule_start_date', '2026-07-01');
    }

    /** @return void */
    public function test_update_clears_watering_schedule_start_date(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create(['watering_schedule_start_date' => '2026-06-29']);

        $this->patchJson("/api/plants/{$plant->id}", [
            'watering_schedule_start_date' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.watering_schedule_start_date', null);
    }

    /** @return void */
    public function test_listing_includes_due_for_care_from_logged_waterings(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);

        $wateringType = CareEventType::where('key', 'watering')->first();
        CareEvent::create([
            'plant_id'           => $plant->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/plants');

        $response->assertOk()
            ->assertJsonPath('data.0.due_for_care.0.type', 'watering')
            ->assertJsonPath('data.0.due_for_care.0.status', 'ok')
            ->assertJsonMissingPath('data.0.due_for_care.0.plant_id')
            ->assertJsonStructure([
                'data' => [['due_for_care' => [['status', 'due_date', 'type', 'daysLeft', 'interval']]]],
            ]);
    }

    /** @return void */
    public function test_listing_returns_empty_due_for_care_when_no_schedule(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create();

        $response = $this->getJson('/api/plants');

        $response->assertOk()
            ->assertJsonPath('data.0.due_for_care', []);
    }

    /** @return void */
    public function test_listing_includes_last_watered_at_when_watering_exists(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $wateringType = CareEventType::where('key', 'watering')->first();
        CareEvent::create([
            'plant_id'           => $plant->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at'        => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/plants');

        $response->assertOk()
            ->assertJsonPath('data.0.last_watered_at', fn ($v) => str_contains($v, now()->subDays(3)->format('Y-m-d')));
    }

    /** @return void */
    public function test_listing_returns_null_last_watered_at_when_no_waterings(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create();

        $response = $this->getJson('/api/plants');

        $response->assertOk()
            ->assertJsonPath('data.0.last_watered_at', null);
    }

    /**
     * @return void
     */
    public function test_the_due_entry_names_the_reading_that_moved_it(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create(['watering_interval_days_override' => 6]);
        $this->logCareEvent($plant, 'watering', now()->subDays(6));
        $this->logCareEvent($plant, 'observation', now())
            ->observation()->create(['soil_moisture_precise' => 8]);

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.due_for_care.0.basis.reading', 8)
            ->assertJsonPath('data.due_for_care.0.basis.source', 'observation')
            ->assertJsonPath('data.due_for_care.0.basis.read_at', now()->format('Y-m-d'))
            ->assertJsonPath('data.due_for_care.0.basis.key', 'override');
    }

    /**
     * Every due entry states what it rests on, including the plants with no
     * soil reading at all: silence there is what made FOL-158 look absent.
     *
     * @return void
     */
    public function test_the_due_entry_still_carries_a_basis_without_a_reading(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create(['watering_interval_days_override' => 6]);
        $this->logCareEvent($plant, 'watering', now()->subDays(6));

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.due_for_care.0.basis.key', 'override')
            ->assertJsonPath('data.due_for_care.0.basis.sample_size', 0)
            ->assertJsonPath('data.due_for_care.0.basis.cadence_days', 6)
            ->assertJsonPath('data.due_for_care.0.basis.learned_days', null)
            ->assertJsonPath('data.due_for_care.0.basis.reading', null)
            ->assertJsonPath('data.due_for_care.0.basis.source', null);
    }

    /**
     * The schedule reads a soil history per plant, so the query count has to
     * stay flat as the collection grows.
     *
     * @return void
     */
    public function test_the_plants_list_does_not_fan_out_per_plant(): void
    {
        $this->actAsHousehold();

        $countFor = function (int $plants): int {
            Plant::query()->forceDelete();

            for ($i = 0; $i < $plants; $i++) {
                $plant = Plant::factory()->create(['watering_interval_days_override' => 6]);
                $this->logCareEvent($plant, 'watering', now()->subDays(6));
                $this->logCareEvent($plant, 'observation', now())
                    ->observation()->create(['soil_moisture_precise' => 8]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/plants')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($countFor(2), $countFor(8));
    }

    /**
     * A plant carrying only a hygrometer can never produce a soil reading,
     * so the list must not touch the reading table on its behalf (FOL-158).
     *
     * @return void
     */
    public function test_the_plants_index_does_not_read_sensor_readings_without_a_probe(): void
    {
        $this->actAsHousehold();
        $sensor = $this->sensorWithReadings(SensorType::Hygrometer, '02:00:5E:BB:00:01');

        Plant::factory()->count(5)->create()->each(
            fn (Plant $plant) => $plant->sensors()->attach($sensor),
        );

        DB::enableQueryLog();
        $this->getJson('/api/plants')->assertOk();

        $this->assertSame([], $this->queriesAgainstReadings(), 'sensor_readings was read for hygrometer-only plants');
    }

    /**
     * And when a probe is attached, the reads are windowed in SQL rather than
     * filtered in PHP after the whole table has been hydrated (FOL-158).
     *
     * @return void
     */
    public function test_the_plants_index_windows_every_sensor_reading_query(): void
    {
        $this->actAsHousehold();
        $probe      = $this->sensorWithReadings(SensorType::Moisture, '02:00:5E:BB:00:02', ['moisture' => 2048]);
        $hygrometer = $this->sensorWithReadings(SensorType::Hygrometer, '02:00:5E:BB:00:03');

        Plant::factory()->count(5)->create()->each(function (Plant $plant) use ($probe, $hygrometer): void {
            $plant->sensors()->attach([$probe->id, $hygrometer->id]);

            // A schedule only exists past the 28 day gate, and without one
            // nothing ever asks for the plant's soil history.
            foreach ([40, 33, 26, 19, 12, 5] as $daysAgo) {
                $this->logCareEvent($plant, 'watering', Carbon::now()->subDays($daysAgo));
            }
        });

        DB::enableQueryLog();
        $this->getJson('/api/plants')->assertOk();

        $reads = $this->queriesAgainstReadings();

        $this->assertNotSame([], $reads, 'the probe should have been read at least once');
        $this->assertLessThanOrEqual(2, count($reads), 'readings must be read once per request, not once per plant');

        foreach ($reads as $query) {
            $this->assertStringContainsString('"recorded_at" >=', $query);
        }
    }

    /**
     * @return list<string>
     */
    private function queriesAgainstReadings(): array
    {
        return array_values(array_map(
            fn (array $entry): string => $entry['query'],
            array_filter(
                DB::getQueryLog(),
                fn (array $entry): bool => str_contains($entry['query'], '"sensor_readings"'),
            ),
        ));
    }

    /**
     * @param SensorType           $type
     * @param string               $mac
     * @param array<string, mixed> $data
     *
     * @return Sensor
     */
    private function sensorWithReadings(SensorType $type, string $mac, array $data = ['humidity' => 50.0, 'temperature' => 20.0]): Sensor
    {
        $sensor = Sensor::create([
            'mac'   => $mac,
            'name'  => $type->value,
            'color' => 'var(--series-1)',
            'type'  => $type,
        ]);

        for ($i = 0; $i < 200; $i++) {
            SensorReading::create([
                'sensor_id'   => $sensor->id,
                'recorded_at' => Carbon::now()->subMinutes(15 * $i),
                'data'        => $data,
            ]);
        }

        return $sensor;
    }

    /**
     * @param Plant  $plant
     * @param string $key
     * @param Carbon $at
     *
     * @return CareEvent
     */
    private function logCareEvent(Plant $plant, string $key, Carbon $at): CareEvent
    {
        return CareEvent::create([
            'plant_id'           => $plant->id,
            'care_event_type_id' => CareEventType::where('key', $key)->value('id'),
            'occurred_at'        => $at,
        ]);
    }

    /**
     * @return User
     */
    private function actAsHousehold(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
