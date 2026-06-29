<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\InspectionRandomReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneInspectionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_old_notifications(): void
    {
        config(['inspection.notification_retention_days' => 30]);

        $user = User::factory()->create();
        $hourSlot = Carbon::parse('2026-06-01 10:00:00');

        $user->notify(new InspectionRandomReminder($hourSlot, 'warning'));

        DB::table('notifications')->update([
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ]);

        $this->artisan('inspections:prune-notifications')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_prune_command_keeps_recent_notifications(): void
    {
        config(['inspection.notification_retention_days' => 30]);

        $user = User::factory()->create();
        $hourSlot = Carbon::parse('2026-06-26 10:00:00');

        $user->notify(new InspectionRandomReminder($hourSlot, 'info'));

        $this->artisan('inspections:prune-notifications')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }
}
