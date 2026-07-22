<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlantStatus;
use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupInsightsApiTest extends TestCase
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
    public function test_group_insights_requires_authentication(): void
    {
        $this->getJson('/api/insights/group?tag=1')->assertUnauthorized();
    }

    /** @return void */
    public function test_group_insights_validates_nonexistent_ids(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/insights/group?tag=9999')->assertUnprocessable();
    }

    /** @return void */
    public function test_group_insights_with_no_filters_returns_all_active_plants(): void
    {
        $this->actAsHousehold();

        $active = Plant::factory()->create();
        Plant::factory()->create(['status' => 'archived']);

        $this->getJson('/api/insights/group')
            ->assertOk()
            ->assertJsonPath('data.group_name', 'All plants')
            ->assertJsonPath('data.plants', [$active->id]);
    }

    /** @return void */
    public function test_group_insights_compares_the_tagged_plants(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Pothos']);

        $golden = Plant::factory()->create([
            'common_name'                        => 'Golden',
            'watering_interval_days_override'    => 7,
            'fertilizing_interval_days_override' => 30,
        ]);
        $golden->tags()->attach($tag);
        $this->observe($golden, overallHealth: 4, daysAgo: 8);
        $this->observe($golden, overallHealth: 2, daysAgo: 1);

        $marble = Plant::factory()->create(['common_name' => 'Marble']);
        $marble->tags()->attach($tag);
        $this->observe($marble, overallHealth: 5, daysAgo: 3);

        // An untagged plant stays out of the group.
        $loner = Plant::factory()->create(['common_name' => 'Loner']);
        $this->observe($loner, overallHealth: 1, daysAgo: 2);

        $response = $this->getJson("/api/insights/group?tag={$tag->id}")->assertOk();

        $response
            ->assertJsonPath('data.tag_id', $tag->id)
            ->assertJsonPath('data.tag_name', 'Pothos')
            ->assertJsonPath('data.plants', [$golden->id, $marble->id])
            ->assertJsonPath('data.correlation_pairs', [])
            ->assertJsonCount(2, 'data.comparison');

        $response
            ->assertJsonPath('data.comparison.0.plant_id', $golden->id)
            ->assertJsonPath('data.comparison.0.common_name', 'Golden')
            ->assertJsonPath('data.comparison.0.watering_interval_days', 7)
            ->assertJsonPath('data.comparison.0.fertilizer_interval_days', 30)
            ->assertJsonPath('data.comparison.0.health_trend.0.value', 4)
            ->assertJsonPath('data.comparison.0.health_trend.1.value', 2)
            ->assertJsonPath('data.comparison.1.plant_id', $marble->id)
            ->assertJsonPath('data.comparison.1.watering_interval_days', null)
            ->assertJsonPath('data.comparison.1.fertilizer_interval_days', null)
            ->assertJsonPath('data.comparison.1.health_trend.0.value', 5);
    }

    /** @return void */
    public function test_group_insights_for_a_tag_with_no_plants_returns_empty_arrays(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Empty']);

        $this->getJson("/api/insights/group?tag={$tag->id}")
            ->assertOk()
            ->assertJsonPath('data.tag_id', $tag->id)
            ->assertJsonPath('data.tag_name', 'Empty')
            ->assertJsonPath('data.plants', [])
            ->assertJsonPath('data.comparison', [])
            ->assertJsonPath('data.correlation_pairs', []);
    }

    /** @return void */
    public function test_group_insights_excludes_archived_plants(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Pothos']);

        $active1 = Plant::factory()->create(['common_name' => 'Active One']);
        $active1->tags()->attach($tag);
        $this->observe($active1, overallHealth: 4, daysAgo: 5);

        $active2 = Plant::factory()->create(['common_name' => 'Active Two']);
        $active2->tags()->attach($tag);
        $this->observe($active2, overallHealth: 3, daysAgo: 3);

        $archived = Plant::factory()->create([
            'common_name' => 'Archived',
            'status'      => PlantStatus::Archived,
        ]);
        $archived->tags()->attach($tag);
        $this->observe($archived, overallHealth: 2, daysAgo: 1);

        $response = $this->getJson("/api/insights/group?tag={$tag->id}")->assertOk();

        $response
            ->assertJsonPath('data.plants', [$active1->id, $active2->id])
            ->assertJsonCount(2, 'data.comparison')
            ->assertJsonPath('data.comparison.0.plant_id', $active1->id)
            ->assertJsonPath('data.comparison.1.plant_id', $active2->id);
    }

    /** @return void */
    public function test_group_insights_reports_a_pooled_watering_interval_correlation(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Pothos']);

        $golden = Plant::factory()->create();
        $golden->tags()->attach($tag);
        $this->water($golden, [50, 44, 35, 27]);
        $this->observe($golden, overallHealth: 5, daysAgo: 43);
        $this->observe($golden, overallHealth: 3, daysAgo: 34);
        $this->observe($golden, overallHealth: 4, daysAgo: 26);

        $marble = Plant::factory()->create();
        $marble->tags()->attach($tag);
        $this->water($marble, [48, 41, 30, 22]);
        $this->observe($marble, overallHealth: 5, daysAgo: 40);
        $this->observe($marble, overallHealth: 2, daysAgo: 29);
        $this->observe($marble, overallHealth: 4, daysAgo: 21);

        $this->getJson("/api/insights/group?tag={$tag->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'correlation_pairs' => [
                        ['x_variable', 'y_variable', 'correlation', 'p_value', 'sample_size', 'confidence_band' => ['lower', 'upper'], 'significant_after_fdr', 'points'],
                    ],
                ],
            ])
            ->assertJsonPath('data.correlation_pairs.0.x_variable', 'watering_interval_days')
            ->assertJsonPath('data.correlation_pairs.0.y_variable', 'overall_health')
            ->assertJsonPath('data.correlation_pairs.0.sample_size', 6)
            ->assertJsonCount(6, 'data.correlation_pairs.0.points');
    }

    /** @return void */
    public function test_group_insights_does_not_crash_on_a_constant_watering_cadence(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Steady']);

        foreach ([1, 2] as $offset) {
            $plant = Plant::factory()->create();
            $plant->tags()->attach($tag);
            $this->water($plant, [50, 43, 36, 29, 22, 15]); // every 7 days: a constant interval
            $this->observe($plant, overallHealth: 3, daysAgo: 40);
            $this->observe($plant, overallHealth: 4, daysAgo: 25);
            $this->observe($plant, overallHealth: 3 + $offset, daysAgo: 10);
        }

        // A fixed cadence makes the pooled interval constant; Spearman must report no correlation
        // rather than dividing by zero and 500ing.
        $this->getJson("/api/insights/group?tag={$tag->id}")
            ->assertOk()
            ->assertJsonPath('data.correlation_pairs.0.x_variable', 'watering_interval_days')
            ->assertJsonPath('data.correlation_pairs.0.sample_size', 6)
            ->assertJsonPath('data.correlation_pairs.0.significant_after_fdr', false);
    }

    /** @return void */
    public function test_group_insights_intersects_tag_and_location_when_both_provided(): void
    {
        $this->actAsHousehold();

        $tag      = Tag::factory()->create();
        $location = Location::factory()->create();
        $plant    = Plant::factory()->create(['location_id' => $location->id]);
        $plant->tags()->attach($tag);

        Plant::factory()->create(['location_id' => $location->id]);

        $this->getJson("/api/insights/group?tag={$tag->id}&location={$location->id}")
            ->assertOk()
            ->assertJsonPath('data.plants', [$plant->id]);
    }

    /** @return void */
    public function test_group_insights_fails_for_nonexistent_location(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/insights/group?location=9999')->assertUnprocessable();
    }

    /** @return void */
    public function test_group_insights_by_tag_includes_tag_fields(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Tropicals']);

        $this->getJson("/api/insights/group?tag={$tag->id}")
            ->assertOk()
            ->assertJsonPath('data.group_name', 'Tropicals')
            ->assertJsonPath('data.tag_id', $tag->id)
            ->assertJsonPath('data.tag_name', 'Tropicals')
            ->assertJsonPath('data.location_id', null)
            ->assertJsonPath('data.location_name', null);
    }

    /** @return void */
    public function test_group_insights_by_location_compares_the_plants_at_that_location(): void
    {
        $this->actAsHousehold();

        $location = Location::factory()->create(['name' => 'Living Room']);

        $fern = Plant::factory()->create([
            'common_name'                        => 'Fern',
            'location_id'                        => $location->id,
            'watering_interval_days_override'    => 5,
            'fertilizing_interval_days_override' => null,
        ]);
        $this->observe($fern, overallHealth: 4, daysAgo: 8);
        $this->observe($fern, overallHealth: 3, daysAgo: 1);

        $palm = Plant::factory()->create([
            'common_name' => 'Palm',
            'location_id' => $location->id,
        ]);
        $this->observe($palm, overallHealth: 5, daysAgo: 3);

        // Plant at a different location stays out.
        $outsider = Plant::factory()->create(['common_name' => 'Outsider']);
        $this->observe($outsider, overallHealth: 2, daysAgo: 2);

        $response = $this->getJson("/api/insights/group?location={$location->id}")->assertOk();

        $response
            ->assertJsonPath('data.location_id', $location->id)
            ->assertJsonPath('data.location_name', 'Living Room')
            ->assertJsonPath('data.plants', [$fern->id, $palm->id])
            ->assertJsonPath('data.correlation_pairs', [])
            ->assertJsonCount(2, 'data.comparison');

        $response
            ->assertJsonPath('data.comparison.0.plant_id', $fern->id)
            ->assertJsonPath('data.comparison.0.common_name', 'Fern')
            ->assertJsonPath('data.comparison.0.watering_interval_days', 5)
            ->assertJsonPath('data.comparison.0.fertilizer_interval_days', null)
            ->assertJsonPath('data.comparison.0.health_trend.0.value', 4)
            ->assertJsonPath('data.comparison.0.health_trend.1.value', 3)
            ->assertJsonPath('data.comparison.1.plant_id', $palm->id)
            ->assertJsonPath('data.comparison.1.health_trend.0.value', 5);
    }

    /** @return void */
    public function test_group_insights_by_location_excludes_archived_and_dead_plants(): void
    {
        $this->actAsHousehold();

        $location = Location::factory()->create(['name' => 'Shelf']);

        $active = Plant::factory()->create([
            'common_name' => 'Active',
            'location_id' => $location->id,
        ]);
        $this->observe($active, overallHealth: 4, daysAgo: 3);

        Plant::factory()->create([
            'common_name' => 'Archived',
            'location_id' => $location->id,
            'status'      => PlantStatus::Archived,
        ]);

        Plant::factory()->create([
            'common_name' => 'Dead',
            'location_id' => $location->id,
            'status'      => PlantStatus::Dead,
        ]);

        $response = $this->getJson("/api/insights/group?location={$location->id}")->assertOk();

        $response
            ->assertJsonPath('data.plants', [$active->id])
            ->assertJsonCount(1, 'data.comparison')
            ->assertJsonPath('data.comparison.0.plant_id', $active->id);
    }

    /** @return void */
    public function test_group_insights_by_location_with_no_active_plants_returns_empty_arrays(): void
    {
        $this->actAsHousehold();

        $location = Location::factory()->create(['name' => 'Empty Room']);

        $this->getJson("/api/insights/group?location={$location->id}")
            ->assertOk()
            ->assertJsonPath('data.location_id', $location->id)
            ->assertJsonPath('data.location_name', 'Empty Room')
            ->assertJsonPath('data.plants', [])
            ->assertJsonPath('data.comparison', [])
            ->assertJsonPath('data.correlation_pairs', []);
    }

    /** @return void */
    public function test_location_summary_requires_authentication(): void
    {
        $this->getJson('/api/insights/locations')->assertUnauthorized();
    }

    /** @return void */
    public function test_location_summary_returns_per_location_mean_health(): void
    {
        $this->actAsHousehold();

        $living  = Location::factory()->create(['name' => 'Living Room']);
        $kitchen = Location::factory()->create(['name' => 'Kitchen']);

        $plant1 = Plant::factory()->create(['location_id' => $living->id]);
        $this->observe($plant1, overallHealth: 4, daysAgo: 3);

        $plant2 = Plant::factory()->create(['location_id' => $living->id]);
        $this->observe($plant2, overallHealth: 2, daysAgo: 5);

        $plant3 = Plant::factory()->create(['location_id' => $kitchen->id]);
        $this->observe($plant3, overallHealth: 5, daysAgo: 1);

        $response   = $this->getJson('/api/insights/locations')->assertOk();
        $byLocation = collect($response->json('data'))->keyBy('location_id');

        $livingData = $byLocation[$living->id];
        $this->assertEquals('Living Room', $livingData['location_name']);
        $this->assertEquals(2, $livingData['plant_count']);
        $this->assertEquals(3.0, $livingData['mean_health']); // (4 + 2) / 2
        $this->assertEquals(2, $livingData['sample_size']);
        $this->assertEqualsCanonicalizing([4, 2], $livingData['health_readings']);

        $kitchenData = $byLocation[$kitchen->id];
        $this->assertEquals('Kitchen', $kitchenData['location_name']);
        $this->assertEquals(1, $kitchenData['plant_count']);
        $this->assertEquals(5.0, $kitchenData['mean_health']);
        $this->assertEquals(1, $kitchenData['sample_size']);
    }

    /** @return void */
    public function test_location_summary_excludes_archived_and_dead_plants(): void
    {
        $this->actAsHousehold();

        $location = Location::factory()->create(['name' => 'Greenhouse']);

        $active = Plant::factory()->create(['location_id' => $location->id]);
        $this->observe($active, overallHealth: 4, daysAgo: 2);

        $archived = Plant::factory()->create([
            'location_id' => $location->id,
            'status'      => PlantStatus::Archived,
        ]);
        $this->observe($archived, overallHealth: 1, daysAgo: 1);

        $response     = $this->getJson('/api/insights/locations')->assertOk();
        $locationData = collect($response->json('data'))->firstWhere('location_id', $location->id);

        $this->assertEquals(1, $locationData['plant_count']);
        $this->assertEquals(4.0, $locationData['mean_health']);
        $this->assertEquals([4], $locationData['health_readings']);
        $this->assertEquals(1, $locationData['sample_size']);
    }

    /** @return void */
    public function test_location_summary_returns_null_mean_for_location_with_no_observations(): void
    {
        $this->actAsHousehold();

        $location = Location::factory()->create(['name' => 'Garage']);
        Plant::factory()->create(['location_id' => $location->id]);

        $response     = $this->getJson('/api/insights/locations')->assertOk();
        $locationData = collect($response->json('data'))->firstWhere('location_id', $location->id);

        $this->assertEquals(1, $locationData['plant_count']);
        $this->assertNull($locationData['mean_health']);
        $this->assertEquals([], $locationData['health_readings']);
        $this->assertEquals(0, $locationData['sample_size']);
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
     * @param Plant     $plant
     * @param integer[] $daysAgo
     *
     * @return void
     */
    private function water(Plant $plant, array $daysAgo): void
    {
        foreach ($daysAgo as $days) {
            $event = CareEvent::factory()->ofType('watering')->for($plant)->create([
                'occurred_at' => now()->subDays($days),
            ]);
            $event->watering()->create(['amount_ml' => 200]);
        }
    }

    /**
     * @param Plant   $plant
     * @param integer $overallHealth
     * @param integer $daysAgo
     *
     * @return void
     */
    private function observe(Plant $plant, int $overallHealth, int $daysAgo): void
    {
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
            'occurred_at' => now()->subDays($daysAgo),
        ]);
        // Null out fields read by humidity, light, and soil-moisture factors so factory defaults
        // don't produce spurious correlation pairs in tests that only intend to test watering.
        Observation::factory()->create([
            'care_event_id'          => $event->id,
            'overall_health'         => $overallHealth,
            'ambient_humidity_pct'   => null,
            'light_level'            => null,
            'soil_moisture_precise'  => null,
            'soil_moisture_relative' => null,
        ]);
    }
}
