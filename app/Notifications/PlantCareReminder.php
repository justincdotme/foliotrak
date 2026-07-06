<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class PlantCareReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param Plant   $plant
     * @param string  $reminderType
     * @param string  $dueOn
     * @param integer $intervalDays
     */
    public function __construct(
        public readonly Plant $plant,
        public readonly string $reminderType,
        public readonly string $dueOn,
        public readonly int $intervalDays,
    ) {}

    /**
     * @param object $notifiable
     *
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [PushoverChannel::class];
    }

    /**
     * @param object $notifiable
     *
     * @return PushoverMessage
     */
    public function toPushover(object $notifiable): PushoverMessage
    {
        $name   = $this->plant->common_name ?? $this->plant->scientific_name ?? 'A plant';
        $action = $this->reminderType === 'fertilizing' ? 'fertilizing' : 'watering';

        return PushoverMessage::create(
            "{$name} is due for {$action} (due {$this->dueOn}, about every {$this->intervalDays} days).",
        )->title('Foliotrak care reminder');
    }
}
