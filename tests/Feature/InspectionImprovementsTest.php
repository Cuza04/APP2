<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\InspectionRandomReminder;
use App\Services\InspectionComplianceService;
use App\Services\InspectionReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\CreatesInspectionFixtures;
use Tests\TestCase;

class InspectionImprovementsTest extends TestCase
{
    use CreatesInspectionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
    }

    public function test_operating_hours_limit_daily_compliance_count(): void
    {
        config([
            'inspection.operating_start_hour' => 6,
            'inspection.operating_end_hour' => 22,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-26 11:30:00', 'America/Bogota'));

        $summary = app(InspectionComplianceService::class)->getDailySummary();

        $this->assertSame(6, $summary['total_due']);
    }

    public function test_reminders_are_not_sent_outside_operating_hours(): void
    {
        config([
            'inspection.operating_start_hour' => 6,
            'inspection.operating_end_hour' => 22,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-26 03:15:00', 'America/Bogota'));

        User::factory()->create(['is_active' => true]);

        $this->assertFalse(app(InspectionReminderService::class)->needsRandomReminder());
    }

    public function test_reminders_skip_inactive_users(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:20:00', 'America/Bogota'));

        User::factory()->inactive()->create();
        $active = User::factory()->create(['is_active' => true]);

        Notification::fake();

        app(InspectionReminderService::class)->sendDatabaseReminders();

        Notification::assertSentTo($active, InspectionRandomReminder::class);
        Notification::assertCount(1);
    }

    public function test_duplicate_pending_assignment_for_same_hour_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:00:00', 'America/Bogota'));

        $user = User::factory()->create();
        $lane = $this->createOpenEntryLane('C1');
        $hourSlot = now()->startOfHour();

        \App\Models\InspectionAssignment::query()->create([
            'lane_id' => $lane->id,
            'requested_by' => $user->id,
            'hour_slot' => $hourSlot,
            'status' => \App\Enums\AssignmentStatus::Pending,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\InspectionAssignment::query()->create([
            'lane_id' => $lane->id,
            'requested_by' => $user->id,
            'hour_slot' => $hourSlot,
            'status' => \App\Enums\AssignmentStatus::Pending,
        ]);
    }
}
