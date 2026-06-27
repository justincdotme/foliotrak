<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GrowthRate;
use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Observation;
use App\Models\Photo;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantTimelineApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CareLookupSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-26 09:00:00'));
    }

    private function actAsHousehold(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_timeline_requires_authentication(): void
    {
        $plant = Plant::factory()->create();

        $this->getJson("/api/plants/{$plant->id}/timeline")->assertUnauthorized();
    }

    public function test_unknown_plant_returns_not_found(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/plants/9999/timeline')->assertNotFound();
    }

    public function test_timeline_returns_the_full_plant_detail_bundle(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create(['common_name' => 'Traveler']);
        $plant->tags()->attach(Tag::factory()->create(['name' => 'Kitchen']));

        $older = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(10)]);
        Observation::factory()->create([
            'care_event_id' => $older->id,
            'overall_health' => 3,
            'weight_grams' => 1000,
            'growth_rate' => GrowthRate::Slow,
        ]);

        $watering = CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays(5)]);
        $watering->watering()->create(['amount_ml' => 200]);

        $newer = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(2)]);
        Observation::factory()->create([
            'care_event_id' => $newer->id,
            'overall_health' => 5,
            'weight_grams' => null,
            'growth_rate' => null,
        ]);

        $relocation = CareEvent::factory()->ofType('relocation')->for($plant)->create(['occurred_at' => now()->subDay()]);
        $shelf = Location::factory()->create(['name' => 'shelf']);
        $window = Location::factory()->create(['name' => 'window']);
        $relocation->relocation()->create(['from_location_id' => $shelf->id, 'to_location_id' => $window->id]);

        Photo::factory()->create(['plant_id' => $plant->id, 'taken_on' => now()->subDays(3)]);

        $response = $this->getJson("/api/plants/{$plant->id}/timeline")->assertOk();

        $response->assertJsonStructure([
            'data' => ['plant', 'events', 'health_trend', 'weight_trend', 'growth_trend', 'recommendations', 'photos'],
        ]);

        $response
            ->assertJsonPath('data.plant.id', $plant->id)
            ->assertJsonPath('data.plant.common_name', 'Traveler')
            ->assertJsonPath('data.plant.condition.key', 'healthy')
            ->assertJsonPath('data.plant.tags.0.name', 'Kitchen')
            ->assertJsonPath('data.recommendations', []);

        // Events are the merged feed, newest first, each carrying its typed detail.
        $response
            ->assertJsonCount(4, 'data.events')
            ->assertJsonPath('data.events.0.type', 'relocation')
            ->assertJsonPath('data.events.0.relocation.to_location.name', 'window')
            ->assertJsonPath('data.events.2.type', 'watering')
            ->assertJsonPath('data.events.2.watering.amount_ml', 200);

        // A watering event carries only its own detail; the non-matching keys are omitted, not null.
        $response
            ->assertJsonMissingPath('data.events.2.observation')
            ->assertJsonMissingPath('data.events.2.fertilizing')
            ->assertJsonMissingPath('data.events.2.relocation')
            ->assertJsonMissingPath('data.events.2.repotting');

        // Trends are chronological (oldest first), dated to the day, null where unrecorded.
        $response
            ->assertJsonCount(2, 'data.health_trend')
            ->assertJsonPath('data.health_trend.0.date', '2026-06-16')
            ->assertJsonPath('data.health_trend.0.value', 3)
            ->assertJsonPath('data.health_trend.1.date', '2026-06-24')
            ->assertJsonPath('data.health_trend.1.value', 5)
            ->assertJsonPath('data.weight_trend.0.value', 1000)
            ->assertJsonPath('data.weight_trend.1.value', null)
            ->assertJsonPath('data.growth_trend.0.value', 'slow')
            ->assertJsonPath('data.growth_trend.1.value', null);

        $response->assertJsonCount(1, 'data.photos');
    }

    public function test_observation_event_without_a_detail_row_yields_a_null_trend_point(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create();
        CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(4)]);

        $this->getJson("/api/plants/{$plant->id}/timeline")
            ->assertOk()
            ->assertJsonCount(1, 'data.health_trend')
            ->assertJsonPath('data.health_trend.0.date', '2026-06-22')
            ->assertJsonPath('data.health_trend.0.value', null);
    }
}
