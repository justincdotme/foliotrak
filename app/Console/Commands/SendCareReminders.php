<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlantStatus;
use App\Models\Plant;
use App\Models\SentReminder;
use App\Models\User;
use App\Notifications\PlantCareReminder;
use App\Support\Care\CareDue;
use App\Support\Care\ScheduledCareType;
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
            foreach (ScheduledCareType::cases() as $type) {
                $dispatched += $this->remind($plant, $type, $recipients);
            }
        }

        $this->info("Dispatched {$dispatched} care reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Claims the day's reminder before dispatch, so a missed or repeated run never
     * sends the same plant, type, and due date twice.
     *
     * @param  Collection<int, User>  $recipients
     */
    private function remind(Plant $plant, ScheduledCareType $type, Collection $recipients): int
    {
        $due = CareDue::for($plant, $type);
        if ($due === null || ! $due->isDue()) {
            return 0;
        }

        $reminder = SentReminder::firstOrCreate(
            ['plant_id' => $plant->id, 'reminder_type' => $type->value, 'due_on' => $due->dueDate->toDateString()],
            ['status' => 'sent', 'sent_at' => Carbon::now()],
        );
        if (! $reminder->wasRecentlyCreated) {
            return 0;
        }

        Notification::send($recipients, new PlantCareReminder($plant, $type->value, $due->dueDate->toDateString(), $due->intervalDays));

        return 1;
    }
}
