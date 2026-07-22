<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantRecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CareLookupSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-26 09:00:00'));
    }

    /** @return void */
    public function test_recommendations_require_authentication(): void
    {
        $plant = Plant::factory()->create();

        $this->getJson("/api/plants/{$plant->id}/recommendations")->assertUnauthorized();
    }

    /** @return void */
    public function test_unknown_plant_returns_not_found(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/plants/9999/recommendations')->assertNotFound();
    }

    /** @return void */
    public function test_a_plant_under_four_weeks_returns_the_countdown_state(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create();
        $this->water($plant, [5, 1]);
        $this->observe($plant, [[3, 4]]);

        $this->getJson("/api/plants/{$plant->id}/recommendations")
            ->assertOk()
            ->assertJsonPath('data.gate.state', 'countdown')
            ->assertJsonPath('data.gate.history_days', 5)
            ->assertJsonPath('data.gate.days_to_go', 23)
            ->assertJsonPath('data.watering', null);
    }

    /** @return void */
    public function test_past_the_gate_without_health_observations_returns_no_health_data(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create();
        $this->water($plant, [40, 33, 26, 19, 12, 5]);

        $this->getJson("/api/plants/{$plant->id}/recommendations")
            ->assertOk()
            ->assertJsonPath('data.gate.state', 'no_health_data')
            ->assertJsonPath('data.watering', null)
            ->assertJsonPath('data.position_insights', []);
    }

    /** @return void */
    public function test_a_seasoned_plant_returns_a_watering_recommendation_with_its_sample_size(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create();
        $this->water($plant, [56, 49, 42, 35, 28, 21, 14, 7], amountMl: 200);
        $this->observe($plant, [[50, 4], [20, 4]]);

        $this->getJson("/api/plants/{$plant->id}/recommendations")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['plant_id', 'gate' => ['state', 'history_days', 'required_days', 'days_to_go'], 'watering', 'position_insights'],
            ])
            ->assertJsonPath('data.gate.state', 'ready')
            ->assertJsonPath('data.gate.days_to_go', 0)
            ->assertJsonPath('data.watering.basis', 'stable')
            ->assertJsonPath('data.watering.interval_days', 7)
            ->assertJsonPath('data.watering.amount_ml', 200)
            ->assertJsonPath('data.watering.sample_size', 8)
            ->assertJsonPath('data.watering.health_sample_size', 2)
            ->assertJsonPath('data.position_insights', []);
    }

    /** @return void */
    public function test_a_move_with_readings_on_each_side_is_reported_as_a_position_insight(): void
    {
        $this->actAsHousehold();
        $shelf   = Location::factory()->create(['name' => 'shelf']);
        $kitchen = Location::factory()->create(['name' => 'kitchen window']);

        $plant = Plant::factory()->create();
        $this->water($plant, [60, 53]);
        $this->observe($plant, [[45, 5], [40, 4], [20, 3], [15, 2]]);

        $move = CareEvent::factory()->ofType('relocation')->for($plant)->create(['occurred_at' => now()->subDays(30)]);
        $move->relocation()->create(['from_location_id' => $shelf->id, 'to_location_id' => $kitchen->id]);

        $this->getJson("/api/plants/{$plant->id}/recommendations")
            ->assertOk()
            ->assertJsonCount(1, 'data.position_insights')
            ->assertJsonPath('data.position_insights.0.from_location.name', 'shelf')
            ->assertJsonPath('data.position_insights.0.to_location.name', 'kitchen window')
            ->assertJsonPath('data.position_insights.0.health_before.median', 4.5)
            ->assertJsonPath('data.position_insights.0.health_before.sample_size', 2)
            ->assertJsonPath('data.position_insights.0.health_after.median', 2.5)
            ->assertJsonPath('data.position_insights.0.health_after.sample_size', 2);
    }

    /** @return void */
    public function test_health_by_location_groups_observations_by_location_at_observation_time(): void
    {
        $this->actAsHousehold();
        $shelf   = Location::factory()->create(['name' => 'shelf']);
        $kitchen = Location::factory()->create(['name' => 'kitchen window']);

        $plant = Plant::factory()->create(['location_id' => $kitchen->id]);
        $this->water($plant, [60, 53]);
        $this->observe($plant, [[45, 5], [40, 4], [20, 3], [15, 2]]);

        $move = CareEvent::factory()->ofType('relocation')->for($plant)->create(['occurred_at' => now()->subDays(30)]);
        $move->relocation()->create(['from_location_id' => $shelf->id, 'to_location_id' => $kitchen->id]);

        // Observations at 45 and 40 days ago predate the move -> shelf bucket (median 4.5)
        // Observations at 20 and 15 days ago postdate the move -> kitchen window bucket (median 2.5)
        // Both buckets have sample_size=2; 'kitchen window' < 'shelf' alphabetically -> kitchen window first
        $this->getJson("/api/plants/{$plant->id}/recommendations")
            ->assertOk()
            ->assertJsonCount(2, 'data.health_by_location')
            ->assertJsonPath('data.health_by_location.0.location.name', 'kitchen window')
            ->assertJsonPath('data.health_by_location.0.sample_size', 2)
            ->assertJsonPath('data.health_by_location.0.median_health', 2.5)
            ->assertJsonPath('data.health_by_location.1.location.name', 'shelf')
            ->assertJsonPath('data.health_by_location.1.sample_size', 2)
            ->assertJsonPath('data.health_by_location.1.median_health', 4.5);
    }

    /** @return void */
    public function test_symptom_episodes_are_included_in_the_response(): void
    {
        $this->actAsHousehold();

        $plant   = Plant::factory()->create();
        $symptom = Symptom::where('key', 'spider_mites')->firstOrFail();

        $event1 = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(10)]);
        $obs1   = Observation::factory()->create(['care_event_id' => $event1->id, 'overall_health' => 3]);
        $obs1->symptoms()->attach($symptom->id);

        $event2 = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(3)]);
        Observation::factory()->create(['care_event_id' => $event2->id, 'overall_health' => 5]);

        $this->getJson("/api/plants/{$plant->id}/recommendations")
            ->assertOk()
            ->assertJsonStructure(['data' => ['symptom_episodes']])
            ->assertJsonCount(1, 'data.symptom_episodes')
            ->assertJsonPath('data.symptom_episodes.0.symptom_key', 'spider_mites')
            ->assertJsonPath('data.symptom_episodes.0.category', 'pest')
            ->assertJsonPath('data.symptom_episodes.0.cleared_at', now()->subDays(3)->toDateString())
            ->assertJsonPath('data.symptom_episodes.0.duration_days', 7)
            ->assertJsonPath('data.symptom_episodes.0.health_at_appear', 3)
            ->assertJsonPath('data.symptom_episodes.0.health_at_clear', 5);
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

    /**
     * @param Plant        $plant
     * @param integer[]    $daysAgo
     * @param integer|null $amountMl
     *
     * @return void
     */
    private function water(Plant $plant, array $daysAgo, ?int $amountMl = null): void
    {
        foreach ($daysAgo as $days) {
            $event = CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays($days)]);
            $event->watering()->create(['amount_ml' => $amountMl]);
        }
    }

    /**
     * @param Plant       $plant
     * @param integer[][] $observations Pairs of [days ago, overall health].
     *
     * @return void
     */
    private function observe(Plant $plant, array $observations): void
    {
        foreach ($observations as [$daysAgo, $health]) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays($daysAgo)]);
            Observation::factory()->create(['care_event_id' => $event->id, 'overall_health' => $health]);
        }
    }
}
