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

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CareLookupSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-26 09:00:00'));
    }

    /** @return void */
    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    /** @return void */
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

    /** @return void */
    public function test_due_for_care_lists_plants_with_a_derivable_interval_sorted_by_urgency(): void
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

        // One event forms no gap and there is no override, so no interval can be derived.
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

    /** @return void */
    public function test_due_for_care_derives_the_interval_from_logged_history_without_an_override(): void
    {
        $this->actAsHousehold();

        // No override, but five waterings seven days apart give 36 days of
        // history and derive a 7-day interval. Last watered 8 days ago, so it
        // is one day overdue.
        $plant = Plant::factory()->create(['common_name' => 'Rhythmic', 'watering_interval_days_override' => null]);

        foreach ([36, 29, 22, 15, 8] as $daysAgo) {
            CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays($daysAgo)]);
        }

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonCount(1, 'data.due_for_care');
        $response
            ->assertJsonPath('data.due_for_care.0.plant_id', $plant->id)
            ->assertJsonPath('data.due_for_care.0.type', 'watering')
            ->assertJsonPath('data.due_for_care.0.interval', 7)
            ->assertJsonPath('data.due_for_care.0.status', 'overdue')
            ->assertJsonPath('data.due_for_care.0.daysLeft', -1);
    }

    /** @return void */
    public function test_due_for_care_omits_a_derived_interval_under_28_days_of_history(): void
    {
        $this->actAsHousehold();

        // The same 7-day rhythm, but the first watering is only 22 days old:
        // below the 28-day gate no median is derived, so nothing is due (FOL-98).
        $plant = Plant::factory()->create(['watering_interval_days_override' => null]);

        foreach ([22, 15, 8] as $daysAgo) {
            CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays($daysAgo)]);
        }

        $this->getJson('/api/dashboard')->assertOk()->assertJsonCount(0, 'data.due_for_care');
    }

    /** @return void */
    public function test_recent_activity_returns_the_eight_newest_events_across_plants(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create(['common_name' => 'Logged']);

        foreach (range(1, 10) as $daysAgo) {
            CareEvent::factory()->ofType('watering')->for($plant)->create([
                'occurred_at' => now()->subDays($daysAgo),
                'note'        => "watering {$daysAgo}",
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

    /** @return void */
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
        $response->assertJsonCount(3, 'data.flagged_problems');

        $flagged = collect($response->json('data.flagged_problems'));
        $this->assertContains(
            ['problem' => 'Low overall health (2/5)', 'severity' => 'alert'],
            $flagged->firstWhere('plant_id', $lowHealth->id)['problems'],
        );
        $this->assertContains(
            ['problem' => 'Root-bound signs', 'severity' => 'warning'],
            $flagged->firstWhere('plant_id', $rootBound->id)['problems'],
        );
        $this->assertContains(
            ['problem' => 'Pest activity', 'severity' => 'alert'],
            $flagged->firstWhere('plant_id', $pest->id)['problems'],
        );
    }

    /** @return void */
    public function test_days_left_counts_midnight_normalized_calendar_days(): void
    {
        $this->actAsHousehold();

        // Watered 5.5 days ago on a 7-day override -> the due moment is 20:30
        // tomorrow, one calendar day away regardless of clock time.
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        CareEvent::factory()->ofType('watering')->for($plant)->create([
            'occurred_at' => now()->subDays(5)->subHours(12),
        ]);

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.due_for_care.0.daysLeft', 1)
            ->assertJsonPath('data.due_for_care.0.status', 'due-soon')
            ->assertJsonPath('data.due_for_care.0.due_date', '2026-06-27');
    }

    /** @return void */
    public function test_a_plant_with_both_overrides_yields_one_due_entry_per_type(): void
    {
        $this->actAsHousehold();

        $plant = Plant::factory()->create([
            'watering_interval_days_override'    => 7,
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

    /** @return void */
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

    /** @return void */
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

        $flagged = collect($response->json('data.flagged_problems'));

        $this->assertContains(
            ['problem' => 'Root rot reported', 'severity' => 'alert'],
            $flagged->firstWhere('plant_id', $rotting->id)['problems'],
        );
        $this->assertContains(
            ['problem' => 'Disease signs', 'severity' => 'alert'],
            $flagged->firstWhere('plant_id', $diseased->id)['problems'],
        );
        $compoundedEntry = $flagged->firstWhere('plant_id', $compounded->id);
        $this->assertContains(
            ['problem' => 'Low overall health (1/5)', 'severity' => 'alert'],
            $compoundedEntry['problems'],
        );
        $this->assertContains(
            ['problem' => 'Pest activity', 'severity' => 'alert'],
            $compoundedEntry['problems'],
        );

        // Health 3 sits above the low-health threshold, so the fair plant produces no flag.
        $this->assertNull($flagged->firstWhere('plant_id', $fair->id));
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
     * @param Plant    $plant
     * @param integer  $overallHealth
     * @param string[] $symptomKeys
     *
     * @return void
     */
    private function observe(Plant $plant, int $overallHealth, array $symptomKeys): void
    {
        $event       = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDay()]);
        $observation = Observation::factory()->create([
            'care_event_id'  => $event->id,
            'overall_health' => $overallHealth,
        ]);

        if ($symptomKeys !== []) {
            $observation->symptoms()->attach(Symptom::whereIn('key', $symptomKeys)->pluck('id'));
        }
    }
}
