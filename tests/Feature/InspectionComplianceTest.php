<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\InspectionResult;
use App\Models\InspectionAssignment;
use App\Models\User;
use App\Services\CloseMissedInspectionHoursService;
use App\Services\InspectionComplianceService;
use App\Services\InspectionRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesInspectionFixtures;
use Tests\TestCase;

class InspectionComplianceTest extends TestCase
{
    use CreatesInspectionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
    }

    public function test_daily_summary_counts_completed_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 11:30:00', 'America/Bogota'));

        $user = User::factory()->create();
        $lane = $this->createOpenEntryLane('C1');
        $service = app(InspectionRandomService::class);

        Carbon::setTestNow(Carbon::parse('2026-06-26 09:15:00', 'America/Bogota'));
        $assignment = $service->generateRandom($user);
        $service->registerInspection($user, $assignment, 'ABC123', InspectionResult::Approved);

        Carbon::setTestNow(Carbon::parse('2026-06-26 11:30:00', 'America/Bogota'));

        $summary = app(InspectionComplianceService::class)->getDailySummary();

        $this->assertSame(1, $summary['completed']);
        $this->assertGreaterThanOrEqual(11, $summary['total_due']);
    }

    public function test_daily_summary_counts_completed_current_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 14:20:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->createOpenEntryLane('C1');
        $service = app(InspectionRandomService::class);

        $assignment = $service->generateRandom($user);
        $service->registerInspection($user, $assignment, 'ABC123', InspectionResult::Approved);

        $summary = app(InspectionComplianceService::class)->getDailySummary();

        $this->assertSame(1, $summary['completed']);
        $this->assertGreaterThanOrEqual(1, $summary['total_due']);
    }

    public function test_close_missed_hours_logs_hours_without_random(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:05:00', 'America/Bogota'));

        User::factory()->create();

        $result = app(CloseMissedInspectionHoursService::class)->run();

        $this->assertGreaterThan(0, $result['logged_missed_hours']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'hour_missed',
        ]);
    }

    public function test_close_missed_hours_cancels_stale_pending_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 15:10:00', 'America/Bogota'));

        $user = User::factory()->create();
        $lane = $this->createOpenEntryLane('C1');

        $assignment = InspectionAssignment::query()->create([
            'lane_id' => $lane->id,
            'requested_by' => $user->id,
            'hour_slot' => Carbon::parse('2026-06-26 14:00:00'),
            'status' => AssignmentStatus::Pending,
        ]);

        $result = app(CloseMissedInspectionHoursService::class)->run();

        $this->assertSame(1, $result['cancelled_assignments']);
        $this->assertSame(AssignmentStatus::Cancelled, $assignment->fresh()->status);
    }

    public function test_rejected_inspection_requires_comments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:00:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->createOpenEntryLane('C1');
        $service = app(InspectionRandomService::class);
        $assignment = $service->generateRandom($user);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->registerInspection($user, $assignment, 'XYZ999', InspectionResult::Rejected);
    }
}
