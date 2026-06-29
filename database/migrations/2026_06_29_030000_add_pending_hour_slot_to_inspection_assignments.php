<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inspection_assignments', 'pending_hour_slot')) {
            return;
        }

        Schema::table('inspection_assignments', function (Blueprint $table) {
            $table->dateTime('pending_hour_slot')->nullable()->after('hour_slot');
        });

        DB::table('inspection_assignments')
            ->where('status', 'pending')
            ->update(['pending_hour_slot' => DB::raw('hour_slot')]);

        Schema::table('inspection_assignments', function (Blueprint $table) {
            $table->unique('pending_hour_slot', 'inspection_assignments_pending_hour_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_assignments', function (Blueprint $table) {
            $table->dropUnique('inspection_assignments_pending_hour_slot_unique');
            $table->dropColumn('pending_hour_slot');
        });
    }
};
