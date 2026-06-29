<?php

namespace App\Console\Commands;

use App\Services\CloseMissedInspectionHoursService;
use Illuminate\Console\Command;

class CloseMissedInspectionHours extends Command
{
    protected $signature = 'inspections:close-missed-hours';

    protected $description = 'Cierra horas vencidas sin inspección y registra horas sin random';

    public function handle(CloseMissedInspectionHoursService $service): int
    {
        $result = $service->run();

        if ($result['cancelled_assignments'] === 0 && $result['logged_missed_hours'] === 0) {
            $this->info('No hay horas incumplidas pendientes de registrar.');

            return self::SUCCESS;
        }

        $this->info("Asignaciones canceladas: {$result['cancelled_assignments']}");
        $this->info("Horas sin random registradas: {$result['logged_missed_hours']}");

        return self::SUCCESS;
    }
}
