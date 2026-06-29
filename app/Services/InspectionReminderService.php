<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\InspectionAssignment;
use App\Models\User;
use App\Notifications\InspectionRandomReminder;
use Illuminate\Support\Carbon;

class InspectionReminderService
{
    public function __construct(
        private InspectionRandomService $randomService,
        private InspectionOperatingHoursService $operatingHours,
    ) {}

    public function needsRandomReminder(?Carbon $hourSlot = null): bool
    {
        if (! $this->operatingHours->isOperatingNow()) {
            return false;
        }

        $hourSlot ??= $this->randomService->currentHourSlot();

        return ! InspectionAssignment::query()
            ->where('hour_slot', $hourSlot)
            ->whereIn('status', [
                AssignmentStatus::Pending,
                AssignmentStatus::Completed,
            ])
            ->exists();
    }

    public function getReminderLevel(): string
    {
        $minutes = now()->minute;
        $urgentMinute = $this->urgentMinuteThreshold();
        $warningMinute = $this->warningMinuteThreshold();

        if ($minutes >= $urgentMinute) {
            return 'urgent';
        }

        if ($minutes >= $warningMinute) {
            return 'warning';
        }

        return 'info';
    }

    public function warningMinuteThreshold(): int
    {
        return config('inspection.reminder_warning_minute', 15);
    }

    public function urgentMinuteThreshold(): int
    {
        return config('inspection.reminder_urgent_minute', 30);
    }

    /**
     * @return array{title: string, body: string, color: string}
     */
    public function getReminderMessage(?string $level = null): array
    {
        $level ??= $this->getReminderLevel();
        $hourSlot = $this->randomService->currentHourSlot();
        $range = sprintf(
            '%s – %s',
            $hourSlot->format('H:i'),
            $hourSlot->copy()->addHour()->format('H:i'),
        );
        $warningMinute = $this->warningMinuteThreshold();
        $urgentMinute = $this->urgentMinuteThreshold();

        return match ($level) {
            'urgent' => [
                'title' => 'Urgente: genera el random',
                'body' => "Llevas más de {$urgentMinute} minutos sin generar el random de las {$range}.",
                'color' => 'danger',
            ],
            'warning' => [
                'title' => 'Recordatorio: random pendiente',
                'body' => "Ya pasaron {$warningMinute} minutos de esta hora sin generar el random ({$range}).",
                'color' => 'warning',
            ],
            default => [
                'title' => 'Nueva hora: genera el random',
                'body' => "Genera el random de inspección para las {$range}.",
                'color' => 'info',
            ],
        };
    }

    public function getReminderBannerMessage(?string $level = null): string
    {
        $level ??= $this->getReminderLevel();
        $warningMinute = $this->warningMinuteThreshold();
        $urgentMinute = $this->urgentMinuteThreshold();

        return match ($level) {
            'urgent' => "Urgente: llevas más de {$urgentMinute} minutos sin generar el random.",
            'warning' => "Recordatorio: ya pasaron {$warningMinute} minutos de esta hora.",
            default => 'Nueva hora: genera el random de inspección.',
        };
    }

    /**
     * @return array{title: string, body: string}
     */
    public function getHourSlotChangedMessage(): array
    {
        $hourSlot = $this->randomService->currentHourSlot();
        $range = sprintf(
            '%s – %s',
            $hourSlot->format('H:i'),
            $hourSlot->copy()->addHour()->format('H:i'),
        );

        return [
            'title' => 'Nueva franja horaria',
            'body' => "Franja {$range}. Genera el random de inspección para esta hora.",
        ];
    }

    public function sendDatabaseReminders(): int
    {
        if (! $this->needsRandomReminder()) {
            return 0;
        }

        $level = $this->getReminderLevel();
        $hourSlot = $this->randomService->currentHourSlot();
        $sent = 0;

        User::query()
            ->where('is_active', true)
            ->each(function (User $user) use ($level, $hourSlot, &$sent): void {
            if ($this->reminderAlreadySent($user, $hourSlot, $level)) {
                return;
            }

            $user->notify(new InspectionRandomReminder($hourSlot, $level));
            $sent++;
        });

        return $sent;
    }

    public function reminderAlreadySent(User $user, Carbon $hourSlot, string $level): bool
    {
        return $user->notifications()
            ->where('type', InspectionRandomReminder::class)
            ->where('created_at', '>=', $hourSlot)
            ->whereJsonContains('data->hour_slot', $hourSlot->toDateTimeString())
            ->whereJsonContains('data->level', $level)
            ->exists();
    }

    public function minutesIntoCurrentHour(): int
    {
        return now()->minute;
    }
}
