<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneInspectionNotifications extends Command
{
    protected $signature = 'inspections:prune-notifications
                            {--days= : Días de retención (default: config inspection.notification_retention_days)}';

    protected $description = 'Elimina notificaciones antiguas del panel';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('inspection.notification_retention_days', 30));

        if ($days < 1) {
            $this->error('Los días de retención deben ser al menos 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $deleted = DB::table('notifications')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Eliminadas {$deleted} notificaciones anteriores a {$cutoff->format('Y-m-d H:i')}.");

        return self::SUCCESS;
    }
}
