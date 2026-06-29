<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Models\User;
use App\Services\CloseMissedInspectionHoursService;
use App\Services\InspectionRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\CreatesInspectionFixtures;
use Tests\TestCase;

class InspectionRobustnessTest extends TestCase
{
    use CreatesInspectionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
    }

    public function test_cannot_generate_random_twice_for_same_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->createOpenEntryLane('C1');
        $service = app(InspectionRandomService::class);

        $service->generateRandom($user);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Ya existe una inspección pendiente para esta hora.');
        $service->generateRandom($user);
    }

    public function test_close_missed_hours_cancels_stale_pending_assignments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 15:10:00', 'America/Bogota'));

        $user = User::factory()->create();
        $lane = $this->createOpenEntryLane('C1');

        $assignment = \App\Models\InspectionAssignment::query()->create([
            'lane_id' => $lane->id,
            'requested_by' => $user->id,
            'hour_slot' => Carbon::parse('2026-06-26 14:00:00'),
            'status' => AssignmentStatus::Pending,
        ]);

        $closed = app(CloseMissedInspectionHoursService::class)->closeStalePendingAssignments();

        $this->assertSame(1, $closed);
        $this->assertSame(AssignmentStatus::Cancelled, $assignment->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'assignment_cancelled',
            'subject_id' => $assignment->id,
        ]);
    }
}
