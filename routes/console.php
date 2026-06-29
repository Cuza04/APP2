<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inspections:send-reminders')->hourlyAt(0);
Schedule::command('inspections:send-reminders')->hourlyAt(15);
Schedule::command('inspections:send-reminders')->hourlyAt(30);
Schedule::command('inspections:send-reminders')->hourlyAt(45);
Schedule::command('inspections:close-missed-hours')->hourlyAt(1);
Schedule::command('inspections:export-daily-report')->dailyAt('23:55');
Schedule::command('inspections:prune-notifications')->dailyAt('03:30');
Schedule::exec('bash scripts/backup-database.sh')->dailyAt('02:00');
