<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['sqlite', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS inspection_assignments_pending_hour_unique ON inspection_assignments (hour_slot) WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['sqlite', 'pgsql'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS inspection_assignments_pending_hour_unique');
    }
};
