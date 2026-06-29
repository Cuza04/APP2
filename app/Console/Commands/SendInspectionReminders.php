<?php

namespace App\Console\Commands;

use App\Services\InspectionReminderService;
use Illuminate\Console\Command;

class SendInspectionReminders extends Command
{
    protected $signature = 'inspections:send-reminders';

    protected $description = 'Envía recordatorios si falta generar el random de la hora actual';

    public function handle(InspectionReminderService $reminderService): int
    {
        if (! $reminderService->needsRandomReminder()) {
            $this->info('No hay recordatorios pendientes para esta hora.');

            return self::SUCCESS;
        }

        $sent = $reminderService->sendDatabaseReminders();

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
