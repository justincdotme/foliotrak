<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlantStatus;
use App\Models\CareEvent;
use App\Models\Plant;
use App\Models\SentReminder;
use App\Models\User;
use App\Notifications\PlantCareReminder;
use App\Support\CareScheduleResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendCareReminders extends Command
{
    protected $signature = 'app:send-care-reminders';

    protected $description = 'Send Pushover reminders for plants due for watering or fertilizing.';

    public function handle(): int
    {
        $recipients = User::query()->whereNotNull('pushover_user_key')->get();
        if ($recipients->isEmpty()) {
            $this->info('No users have a Pushover key; nothing to send.');

            return self::SUCCESS;
        }

        $plants = Plant::query()
            ->where('status', PlantStatus::Active->value)
            ->with(['wateringEvents', 'fertilizingEvents'])
            ->get();

        $dispatched = 0;
        foreach ($plants as $plant) {
            $dispatched += $this->remind($plant, 'watering', $plant->watering_interval_days_override, $plant->wateringEvents, $recipients, $plant->watering_schedule_start_date);
            $dispatched += $this->remind($plant, 'fertilizing', $plant->fertilizing_interval_days_override, $plant->fertilizingEvents, $recipients);
        }

        $this->info("Dispatched {$dispatched} care reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Claims the day's reminder before dispatch, so a missed or repeated run never
     * sends the same plant, type, and due date twice.
     *
     * @param  Collection<int, CareEvent>  $events  every logged event of the type, oldest first
     * @param  Collection<int, User>  $recipients
     */
    private function remind(Plant $plant, string $type, ?int $override, Collection $events, Collection $recipients, ?Carbon $startDate = null): int
    {
        $interval = CareScheduleResolver::intervalForType(
            $override,
            $events->map(fn (CareEvent $event): Carbon => $event->occurred_at)->all(),
        );

        if ($interval === null) {
            return 0;
        }

        $anchor = $events->isEmpty() ? $startDate : $events->last()->occurred_at;

        if ($anchor === null) {
            return 0;
        }

        $due = $anchor->copy()->addDays($interval);
        if ($due->startOfDay()->greaterThan(Carbon::today())) {
            return 0;
        }

        $reminder = SentReminder::firstOrCreate(
            ['plant_id' => $plant->id, 'reminder_type' => $type, 'due_on' => $due->toDateString()],
            ['status' => 'sent', 'sent_at' => Carbon::now()],
        );
        if (! $reminder->wasRecentlyCreated) {
            return 0;
        }

        Notification::send($recipients, new PlantCareReminder($plant, $type, $due->toDateString(), $interval));

        return 1;
    }
}
