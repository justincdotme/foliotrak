<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
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

    public function test_group_insights_requires_authentication(): void
    {
        $this->getJson('/api/insights/group?tag=1')->assertUnauthorized();
    }

    public function test_group_insights_validates_the_tag(): void
    {
        $this->actAsHousehold();

        $this->getJson('/api/insights/group')->assertUnprocessable();
        $this->getJson('/api/insights/group?tag=9999')->assertUnprocessable();
    }

    public function test_group_insights_compares_the_tagged_plants(): void
    {
        $this->actAsHousehold();

        $tag = Tag::factory()->create(['name' => 'Pothos']);

        $golden = Plant::factory()->create([
            'common_name' => 'Golden',
            'watering_interval_days_override' => 7,
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

    private function observe(Plant $plant, int $overallHealth, int $daysAgo): void
    {
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
            'occurred_at' => now()->subDays($daysAgo),
        ]);
        Observation::factory()->create([
            'care_event_id' => $event->id,
            'overall_health' => $overallHealth,
        ]);
    }
}
