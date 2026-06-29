<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\InspectionResult;
use App\Enums\LaneDirection;
use App\Enums\LaneStatus;
use App\Enums\LaneType;
use App\Models\InspectionAssignment;
use App\Models\InspectionLane;
use App\Models\User;
use App\Services\InspectionRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesInspectionFixtures;
use Tests\TestCase;

class InspectionRandomServiceTest extends TestCase
{
    use CreatesInspectionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
    }

    public function test_current_hour_slot_uses_configured_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 14:30:00', 'America/Bogota'));

        $hourSlot = app(InspectionRandomService::class)->currentHourSlot();

        $this->assertSame('2026-06-26 14:00:00', $hourSlot->format('Y-m-d H:i:s'));
        $this->assertSame('America/Bogota', $hourSlot->timezone->getName());
    }

    public function test_get_current_hour_assignment_returns_completed_inspection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:15:00', 'America/Bogota'));

        $user = User::factory()->create();
        $lane = $this->createOpenEntryLane();
        $service = app(InspectionRandomService::class);

        $assignment = $service->generateRandom($user);
        $service->registerInspection($user, $assignment, 'ABC123', InspectionResult::Approved);

        $active = $service->getActiveAssignment();
        $current = $service->getCurrentHourAssignment();

        $this->assertNull($active);
        $this->assertNotNull($current);
        $this->assertSame(AssignmentStatus::Completed, $current->status);
        $this->assertSame('ABC123', $current->inspection->plate);
    }

    public function test_get_current_hour_assignment_returns_pending_when_not_completed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 11:00:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->createOpenEntryLane();
        $service = app(InspectionRandomService::class);

        $assignment = $service->generateRandom($user);

        $this->assertSame($assignment->id, $service->getActiveAssignment()?->id);
        $this->assertSame($assignment->id, $service->getCurrentHourAssignment()?->id);
        $this->assertSame(AssignmentStatus::Pending, $service->getCurrentHourAssignment()->status);
    }
}
