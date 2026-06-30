<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlantStatus;
use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Location;
use App\Models\Photo;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A location change logs a relocation, which needs the care-event types.
        $this->seed(CareLookupSeeder::class);
    }

    private function actAsHousehold(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_listing_plants_requires_authentication(): void
    {
        $this->getJson('/api/plants')->assertUnauthorized();
    }

    public function test_creates_a_plant_and_returns_the_contract_shape(): void
    {
        $this->actAsHousehold();
        $south = Location::factory()->create(['name' => 'south window']);

        $response = $this->postJson('/api/plants', [
            'common_name' => 'Swiss cheese plant',
            'scientific_name' => 'Monstera deliciosa',
            'gbif_key' => '2868125',
            'location_id' => $south->id,
            'acquired_on' => '2026-01-15',
            'notes' => 'Repotted on arrival.',
            'watering_interval_days_override' => 7,
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
            'common_name' => 'Swiss cheese plant',
            'scientific_name' => 'Monstera deliciosa',
            'status' => 'active',
        ]);
    }

    public function test_defaults_status_to_active_when_omitted(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/plants', ['common_name' => 'Pothos'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');
    }

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

    public function test_updates_plant_attributes_including_location(): void
    {
        $this->actAsHousehold();
        $south = Location::factory()->create(['name' => 'south window']);
        $east = Location::factory()->create(['name' => 'east window']);
        $plant = Plant::factory()->create(['location_id' => $south->id, 'status' => PlantStatus::Active]);

        $this->patchJson("/api/plants/{$plant->id}", [
            'location_id' => $east->id,
            'status' => 'archived',
            'notes' => 'Moved for winter light.',
            'watering_interval_days_override' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.location.name', 'east window')
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.watering_interval_days_override', 10);

        $this->assertDatabaseHas('plants', [
            'id' => $plant->id,
            'location_id' => $east->id,
            'status' => 'archived',
        ]);
    }

    public function test_rejects_an_invalid_status(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->patchJson("/api/plants/{$plant->id}", ['status' => 'thriving'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('status');
    }

    public function test_deleting_a_plant_soft_deletes_it(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->deleteJson("/api/plants/{$plant->id}")->assertNoContent();

        $this->assertSoftDeleted('plants', ['id' => $plant->id]);
        $this->getJson('/api/plants')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_attaches_tags_when_creating_a_plant(): void
    {
        $this->actAsHousehold();
        $pothos = Tag::factory()->create(['name' => 'Pothos']);
        $kitchen = Tag::factory()->create(['name' => 'Kitchen']);

        $response = $this->postJson('/api/plants', [
            'common_name' => 'Golden pothos',
            'tag_ids' => [$pothos->id, $kitchen->id],
        ])->assertCreated();

        $names = collect($response->json('data.tags'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Pothos', 'Kitchen'], $names);
        $this->assertDatabaseHas('plant_tag', ['plant_id' => $response->json('data.id'), 'tag_id' => $pothos->id]);
    }

    public function test_syncs_tags_on_update_and_leaves_them_alone_when_omitted(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $old = Tag::factory()->create(['name' => 'Old']);
        $new = Tag::factory()->create(['name' => 'New']);
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

    public function test_filters_plants_by_tag(): void
    {
        $this->actAsHousehold();
        $kitchen = Tag::factory()->create(['name' => 'Kitchen']);
        $tagged = Plant::factory()->create(['common_name' => 'On the sill']);
        $tagged->tags()->attach($kitchen);
        Plant::factory()->create(['common_name' => 'In the office']);

        $this->getJson("/api/plants?tag={$kitchen->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.common_name', 'On the sill');
    }

    public function test_sets_an_existing_photo_as_cover_via_patch(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $photo = Photo::factory()->for($plant)->create();

        $this->patchJson("/api/plants/{$plant->id}", ['cover_photo_id' => $photo->id])
            ->assertOk()
            ->assertJsonPath('data.cover_photo_id', $photo->id);
    }

    public function test_rejects_a_cover_photo_belonging_to_another_plant(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $foreignPhoto = Photo::factory()->create(); // different plant

        $this->patchJson("/api/plants/{$plant->id}", ['cover_photo_id' => $foreignPhoto->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('cover_photo_id');
    }

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

    public function test_cover_photo_is_null_when_the_plant_has_none(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create(['cover_photo_id' => null]);

        $this->getJson('/api/plants')
            ->assertOk()
            ->assertJsonPath('data.0.cover_photo', null);
    }

    public function test_store_accepts_watering_schedule_start_date(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/plants', [
            'common_name' => 'Fern',
            'watering_schedule_start_date' => '2026-06-29',
        ])
            ->assertCreated()
            ->assertJsonPath('data.watering_schedule_start_date', '2026-06-29');
    }

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

    public function test_listing_includes_due_for_care_from_logged_waterings(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);

        $wateringType = CareEventType::where('key', 'watering')->first();
        CareEvent::create([
            'plant_id' => $plant->id,
            'care_event_type_id' => $wateringType->id,
            'occurred_at' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/plants');

        $response->assertOk()
            ->assertJsonPath('data.0.due_for_care.0.type', 'watering')
            ->assertJsonPath('data.0.due_for_care.0.status', 'ok')
            ->assertJsonStructure([
                'data' => [['due_for_care' => [['plant_id', 'status', 'due_date', 'type', 'daysLeft', 'interval']]]],
            ]);
    }

    public function test_listing_returns_empty_due_for_care_when_no_schedule(): void
    {
        $this->actAsHousehold();
        Plant::factory()->create();

        $response = $this->getJson('/api/plants');

        $response->assertOk()
            ->assertJsonPath('data.0.due_for_care', []);
    }
}
