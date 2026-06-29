<?php

namespace App\Notifications;

use App\Services\InspectionReminderService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class InspectionRandomReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Carbon $hourSlot,
        public string $level,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $message = app(InspectionReminderService::class)->getReminderMessage($this->level);

        return array_merge(
            FilamentNotification::make()
                ->title($message['title'])
                ->body($message['body'])
                ->{$message['color']}()
                ->getDatabaseMessage(),
            [
                'hour_slot' => $this->hourSlot->toDateTimeString(),
                'level' => $this->level,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'hour_slot' => $this->hourSlot->toDateTimeString(),
            'level' => $this->level,
        ];
    }
}
