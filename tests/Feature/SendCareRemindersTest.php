<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Models\User;
use App\Notifications\PlantCareReminder;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendCareRemindersTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_KEY = 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-26 08:00:00'));
    }

    private function userWithKey(string $key = self::SAMPLE_KEY): User
    {
        return User::factory()->create(['pushover_user_key' => $key]);
    }

    private function wateredDaysAgo(Plant $plant, int ...$daysAgo): void
    {
        foreach ($daysAgo as $days) {
            CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays($days)]);
        }
    }

    public function test_a_plant_past_its_override_interval_sends_one_reminder(): void
    {
        Notification::fake();
        $this->userWithKey();

        // 7-day override, watered 8 days ago -> due 2026-06-25, one day overdue.
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseHas('sent_reminders', [
            'plant_id' => $plant->id,
            'reminder_type' => 'watering',
            'due_on' => '2026-06-25',
            'status' => 'sent',
        ]);
    }

    public function test_a_second_run_the_same_day_sends_nothing_more(): void
    {
        Notification::fake();
        $this->userWithKey();
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();
        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseCount('sent_reminders', 1);
    }

    public function test_a_reminder_fans_out_to_every_user_with_a_key(): void
    {
        Notification::fake();
        $first = $this->userWithKey('aQiRzpo4DXghDmr9QzzfQu27cmVRsG');
        $second = $this->userWithKey('bQiRzpo4DXghDmr9QzzfQu27cmVRsG');
        $without = User::factory()->create(['pushover_user_key' => null]);

        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTo([$first, $second], PlantCareReminder::class);
        Notification::assertNotSentTo([$without], PlantCareReminder::class);
        // One claim covers every recipient for that plant, type, and day.
        $this->assertDatabaseCount('sent_reminders', 1);
    }

    public function test_a_plant_not_yet_due_sends_nothing(): void
    {
        Notification::fake();
        $this->userWithKey();
        $plant = Plant::factory()->create(['watering_interval_days_override' => 30]);
        $this->wateredDaysAgo($plant, 3);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('sent_reminders', 0);
    }

    public function test_a_derived_interval_drives_a_reminder_without_an_override(): void
    {
        Notification::fake();
        $this->userWithKey();

        // No override, but a 7-day rhythm with 36 days of history; the last
        // watering 8 days ago is overdue.
        $plant = Plant::factory()->create(['watering_interval_days_override' => null]);
        $this->wateredDaysAgo($plant, 36, 29, 22, 15, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseHas('sent_reminders', [
            'plant_id' => $plant->id,
            'reminder_type' => 'watering',
            'due_on' => '2026-06-25',
        ]);
    }

    public function test_history_under_28_days_drives_no_reminder_without_an_override(): void
    {
        Notification::fake();
        $this->userWithKey();

        // The same 7-day rhythm, but the first watering is only 22 days old:
        // below the 28-day gate no interval is derived (FOL-98).
        $plant = Plant::factory()->create(['watering_interval_days_override' => null]);
        $this->wateredDaysAgo($plant, 22, 15, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('sent_reminders', 0);
    }

    public function test_a_single_event_with_no_override_cannot_derive_a_due_date(): void
    {
        Notification::fake();
        $this->userWithKey();
        $plant = Plant::factory()->create(['watering_interval_days_override' => null]);
        $this->wateredDaysAgo($plant, 40);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('sent_reminders', 0);
    }

    public function test_nothing_is_claimed_when_no_user_has_a_key(): void
    {
        Notification::fake();
        User::factory()->create(['pushover_user_key' => null]);
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('sent_reminders', 0);
    }

    public function test_archived_plants_do_not_trigger_reminders(): void
    {
        Notification::fake();
        $this->userWithKey();
        $plant = Plant::factory()->create(['status' => 'archived', 'watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 30);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_new_due_date_after_watering_starts_a_fresh_reminder_cycle(): void
    {
        Notification::fake();
        $this->userWithKey();
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 8);

        $this->artisan('app:send-care-reminders')->assertSuccessful();
        Notification::assertSentTimes(PlantCareReminder::class, 1);

        // Logging a watering today moves the next due date to 2026-07-03.
        $this->wateredDaysAgo($plant, 0);
        $this->artisan('app:send-care-reminders')->assertSuccessful();

        // Still only the original reminder; the new due date is not yet reached.
        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseCount('sent_reminders', 1);

        // On the new due date a second reminder fires, proving the due date is
        // recomputed from the latest event rather than reusing the stale one.
        $this->travelTo(Carbon::parse('2026-07-03 08:00:00'));
        $this->artisan('app:send-care-reminders')->assertSuccessful();
        Notification::assertSentTimes(PlantCareReminder::class, 2);
        $this->assertDatabaseHas('sent_reminders', [
            'plant_id' => $plant->id,
            'reminder_type' => 'watering',
            'due_on' => '2026-07-03',
        ]);
        $this->assertDatabaseCount('sent_reminders', 2);
    }

    public function test_a_plant_due_exactly_today_is_reminded(): void
    {
        Notification::fake();
        $this->userWithKey();

        // Override 7, watered exactly 7 days ago, so it is due today (2026-06-26).
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        $this->wateredDaysAgo($plant, 7);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseHas('sent_reminders', [
            'plant_id' => $plant->id,
            'reminder_type' => 'watering',
            'due_on' => '2026-06-26',
        ]);
    }

    public function test_a_fertilizing_due_plant_sends_a_fertilizing_reminder(): void
    {
        Notification::fake();
        $this->userWithKey();

        // Fertilized 31 days ago on a 30-day override, so it is one day overdue.
        $plant = Plant::factory()->create(['fertilizing_interval_days_override' => 30]);
        CareEvent::factory()->ofType('fertilizing')->for($plant)->create(['occurred_at' => now()->subDays(31)]);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseHas('sent_reminders', [
            'plant_id' => $plant->id,
            'reminder_type' => 'fertilizing',
            'status' => 'sent',
        ]);
    }

    public function test_a_plant_with_start_date_and_interval_but_no_events_sends_a_reminder_when_due(): void
    {
        Notification::fake();
        $this->userWithKey();

        $plant = Plant::factory()->create([
            'watering_interval_days_override' => 5,
            'watering_schedule_start_date' => '2026-06-20',
        ]);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 1);
        $this->assertDatabaseHas('sent_reminders', [
            'plant_id' => $plant->id,
            'reminder_type' => 'watering',
            'due_on' => '2026-06-25',
        ]);
    }

    public function test_a_plant_with_start_date_and_interval_not_yet_due_sends_nothing(): void
    {
        Notification::fake();
        $this->userWithKey();

        Plant::factory()->create([
            'watering_interval_days_override' => 10,
            'watering_schedule_start_date' => '2026-06-26',
        ]);

        $this->artisan('app:send-care-reminders')->assertSuccessful();

        Notification::assertSentTimes(PlantCareReminder::class, 0);
    }

    public function test_the_pushover_message_names_the_plant_and_the_action(): void
    {
        $plant = Plant::factory()->make(['common_name' => 'Fern']);
        $notifiable = User::factory()->make();

        $watering = (new PlantCareReminder($plant, 'watering', '2026-06-25', 7))->toPushover($notifiable)->toArray();
        $this->assertStringContainsString('Fern', $watering['message']);
        $this->assertStringContainsString('watering', $watering['message']);
        $this->assertStringContainsString('7', $watering['message']);

        $fertilizing = (new PlantCareReminder($plant, 'fertilizing', '2026-06-25', 30))->toPushover($notifiable)->toArray();
        $this->assertStringContainsString('fertilizing', $fertilizing['message']);
    }

    public function test_the_pushover_message_falls_back_when_the_plant_is_unnamed(): void
    {
        $plant = Plant::factory()->make(['common_name' => null, 'scientific_name' => null]);
        $notifiable = User::factory()->make();

        $message = (new PlantCareReminder($plant, 'watering', '2026-06-25', 7))->toPushover($notifiable)->toArray();

        $this->assertStringContainsString('A plant', $message['message']);
    }

    public function test_pushover_routing_resolves_to_the_user_key(): void
    {
        $user = $this->userWithKey('zQiRzpo4DXghDmr9QzzfQu27cmVRsG');

        // Laravel resolves the recipient through this channel hook by name.
        $this->assertSame('zQiRzpo4DXghDmr9QzzfQu27cmVRsG', $user->routeNotificationFor('pushover'));
    }

    public function test_the_reminder_command_is_scheduled_daily_at_eight(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn ($scheduled) => str_contains((string) $scheduled->command, 'send-care-reminders'),
        );

        $this->assertNotNull($event, 'The care-reminder command is not scheduled.');
        $this->assertSame('0 8 * * *', $event->expression);
    }
}
