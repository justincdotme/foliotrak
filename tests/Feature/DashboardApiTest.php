<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
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

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_the_current_user_and_the_four_sections(): void
    {
        $user = $this->actAsHousehold();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.pushover_user_key', null)
            ->assertJsonStructure([
                'data' => ['user', 'due_for_care', 'recent_activity', 'flagged_problems'],
            ]);
    }

    public function test_due_for_care_is_override_driven_and_sorted_by_urgency(): void
    {
        $this->actAsHousehold();

        // Overdue watering: watered 9 days ago on a 7-day override -> due 2 days ago.
        $overdue = Plant::factory()->create(['common_name' => 'Thirsty', 'watering_interval_days_override' => 7]);
        CareEvent::factory()->ofType('watering')->for($overdue)->create(['occurred_at' => now()->subDays(9)]);

        // Comfortably ok watering: watered 3 days ago on a 10-day override -> due in 7 days.
        $ok = Plant::factory()->create(['common_name' => 'Settled', 'watering_interval_days_override' => 10]);
        CareEvent::factory()->ofType('watering')->for($ok)->create(['occurred_at' => now()->subDays(3)]);

        // Due-soon fertilizing: fed 29 days ago on a 30-day override -> due tomorrow.
        $soon = Plant::factory()->create(['common_name' => 'Hungry', 'fertilizing_interval_days_override' => 30]);
        CareEvent::factory()->ofType('fertilizing')->for($soon)->create(['occurred_at' => now()->subDays(29)]);

        // No override means no interval to derive a due date from, so this plant is excluded.
        $noOverride = Plant::factory()->create(['watering_interval_days_override' => null]);
        CareEvent::factory()->ofType('watering')->for($noOverride)->create(['occurred_at' => now()->subDays(20)]);

        // Override but no logged event of that type -> nothing to derive a due date from.
        Plant::factory()->create(['watering_interval_days_override' => 7]);

        // Archived plants stay off the dashboard.
        $archived = Plant::factory()->create(['status' => 'archived', 'watering_interval_days_override' => 7]);
        CareEvent::factory()->ofType('watering')->for($archived)->create(['occurred_at' => now()->subDays(30)]);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonCount(3, 'data.due_for_care');

        // Sorted by daysLeft ascending: overdue (-2), then due-soon (1), then ok (7).
        $response
            ->assertJsonPath('data.due_for_care.0.plant_id', $overdue->id)
            ->assertJsonPath('data.due_for_care.0.type', 'watering')
            ->assertJsonPath('data.due_for_care.0.status', 'overdue')
            ->assertJsonPath('data.due_for_care.0.daysLeft', -2)
            ->assertJsonPath('data.due_for_care.0.interval', 7)
            ->assertJsonPath('data.due_for_care.0.due_date', '2026-06-24')
            ->assertJsonPath('data.due_for_care.0.common_name', 'Thirsty')
            ->assertJsonPath('data.due_for_care.1.plant_id', $soon->id)
            ->assertJsonPath('data.due_for_care.1.type', 'fertilizing')
            ->assertJsonPath('data.due_for_care.1.status', 'due-soon')
            ->assertJsonPath('data.due_for_care.1.daysLeft', 1)
            ->assertJsonPath('data.due_for_care.2.plant_id', $ok->id)
            ->assertJsonPath('data.due_for_care.2.status', 'ok')
            ->assertJsonPath('data.due_for_care.2.daysLeft', 7);
    }

    public function test_recent_activity_returns_the_eight_newest_events_across_plants(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create(['common_name' => 'Logged']);
        foreach (range(1, 10) as $daysAgo) {
            CareEvent::factory()->ofType('watering')->for($plant)->create([
                'occurred_at' => now()->subDays($daysAgo),
                'note' => "watering {$daysAgo}",
            ]);
        }

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonCount(8, 'data.recent_activity');
        $response
            ->assertJsonPath('data.recent_activity.0.note', 'watering 1')
            ->assertJsonPath('data.recent_activity.0.type', 'watering')
            ->assertJsonPath('data.recent_activity.0.plant_id', $plant->id)
            ->assertJsonPath('data.recent_activity.0.plant_common_name', 'Logged')
            ->assertJsonPath('data.recent_activity.7.note', 'watering 8');
    }

    public function test_flagged_problems_surface_observation_signals(): void
    {
        $this->actAsHousehold();

        $lowHealth = Plant::factory()->create(['common_name' => 'Fading']);
        $this->observe($lowHealth, overallHealth: 2, symptomKeys: []);

        $rootBound = Plant::factory()->create(['common_name' => 'Cramped']);
        $this->observe($rootBound, overallHealth: 4, symptomKeys: ['root_bound']);

        $pest = Plant::factory()->create(['common_name' => 'Bitten']);
        $this->observe($pest, overallHealth: 4, symptomKeys: ['spider_mites']);

        $healthy = Plant::factory()->create(['common_name' => 'Fine']);
        $this->observe($healthy, overallHealth: 5, symptomKeys: []);

        $response = $this->getJson('/api/dashboard')->assertOk();

        // The healthy plant produces no flag, so exactly the three troubled plants appear.
        $response
            ->assertJsonCount(3, 'data.flagged_problems')
            ->assertJsonFragment(['plant_id' => $lowHealth->id, 'problem' => 'Low overall health (2/5)', 'severity' => 'alert'])
            ->assertJsonFragment(['plant_id' => $rootBound->id, 'problem' => 'Root-bound signs', 'severity' => 'warning'])
            ->assertJsonFragment(['plant_id' => $pest->id, 'problem' => 'Pest activity', 'severity' => 'alert']);
    }

    public function test_days_left_rounds_the_fractional_delta_to_whole_days(): void
    {
        $this->actAsHousehold();

        // Watered 5.5 days ago on a 7-day override -> due 1.5 days out -> rounds to 2 -> ok.
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        CareEvent::factory()->ofType('watering')->for($plant)->create([
            'occurred_at' => now()->subDays(5)->subHours(12),
        ]);

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.due_for_care.0.daysLeft', 2)
            ->assertJsonPath('data.due_for_care.0.status', 'ok')
            ->assertJsonPath('data.due_for_care.0.due_date', '2026-06-27');
    }

    public function test_a_plant_with_both_overrides_yields_one_due_entry_per_type(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create([
            'watering_interval_days_override' => 7,
            'fertilizing_interval_days_override' => 30,
        ]);
        CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays(8)]);
        CareEvent::factory()->ofType('fertilizing')->for($plant)->create(['occurred_at' => now()->subDays(10)]);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonCount(2, 'data.due_for_care');
        $entries = collect($response->json('data.due_for_care'));
        $this->assertSame(['fertilizing', 'watering'], $entries->pluck('type')->sort()->values()->all());
        $this->assertSame([$plant->id], $entries->pluck('plant_id')->unique()->values()->all());
    }

    public function test_recent_activity_spans_plants_and_excludes_trashed_ones(): void
    {
        $this->actAsHousehold();

        $active = Plant::factory()->create();
        CareEvent::factory()->ofType('watering')->for($active)->create(['occurred_at' => now()->subDay(), 'note' => 'active event']);

        $archived = Plant::factory()->create(['status' => 'archived']);
        CareEvent::factory()->ofType('watering')->for($archived)->create(['occurred_at' => now()->subDays(2), 'note' => 'archived event']);

        $trashed = Plant::factory()->create();
        CareEvent::factory()->ofType('watering')->for($trashed)->create(['occurred_at' => now()->subHour(), 'note' => 'trashed event']);
        $trashed->delete();

        $notes = collect($this->getJson('/api/dashboard')->assertOk()->json('data.recent_activity'))->pluck('note');

        $this->assertContains('active event', $notes);
        $this->assertContains('archived event', $notes);
        $this->assertNotContains('trashed event', $notes);
    }

    public function test_flagged_problems_cover_root_rot_disease_and_multiple_signals(): void
    {
        $this->actAsHousehold();

        $rotting = Plant::factory()->create();
        $this->observe($rotting, overallHealth: 4, symptomKeys: ['root_rot']);

        $diseased = Plant::factory()->create();
        $this->observe($diseased, overallHealth: 4, symptomKeys: ['powdery_mildew']);

        $compounded = Plant::factory()->create();
        $this->observe($compounded, overallHealth: 1, symptomKeys: ['spider_mites']);

        $fair = Plant::factory()->create();
        $this->observe($fair, overallHealth: 3, symptomKeys: []);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response
            ->assertJsonFragment(['plant_id' => $rotting->id, 'problem' => 'Root rot reported', 'severity' => 'alert'])
            ->assertJsonFragment(['plant_id' => $diseased->id, 'problem' => 'Disease signs', 'severity' => 'alert'])
            ->assertJsonFragment(['plant_id' => $compounded->id, 'problem' => 'Low overall health (1/5)', 'severity' => 'alert'])
            ->assertJsonFragment(['plant_id' => $compounded->id, 'problem' => 'Pest activity', 'severity' => 'alert']);

        // Health 3 sits above the low-health threshold, so the fair plant produces no flag.
        $fairFlags = collect($response->json('data.flagged_problems'))->where('plant_id', $fair->id);
        $this->assertCount(0, $fairFlags);
    }

    /**
     * @param  list<string>  $symptomKeys
     */
    private function observe(Plant $plant, int $overallHealth, array $symptomKeys): void
    {
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDay()]);
        $observation = Observation::factory()->create([
            'care_event_id' => $event->id,
            'overall_health' => $overallHealth,
        ]);

        if ($symptomKeys !== []) {
            $observation->symptoms()->attach(Symptom::whereIn('key', $symptomKeys)->pluck('id'));
        }
    }
}
