<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\InspectionResult;
use App\Enums\LaneDirection;
use App\Enums\LaneStatus;
use App\Enums\LaneType;
use App\Filament\Resources\InspectionLaneResource;
use App\Models\Inspection;
use App\Models\InspectionAssignment;
use App\Models\InspectionLane;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\InspectionRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InspectionOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_lane_is_excluded_from_random(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 09:00:00'));

        $user = User::factory()->admin()->create();
        $lane = $this->createOpenEntryLane('C1');

        $this->actingAs($user);

        InspectionLaneResource::updateLaneStatus($lane, LaneStatus::Closed);

        $service = app(InspectionRandomService::class);

        $this->assertCount(0, $service->getEligibleLanes());
    }

    public function test_lane_status_change_is_logged(): void
    {
        $user = User::factory()->admin()->create();
        $lane = $this->createOpenEntryLane('C2');

        $this->actingAs($user);

        InspectionLaneResource::updateLaneStatus($lane, LaneStatus::Maintenance);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'lane_status_changed',
            'subject_type' => $lane->getMorphClass(),
            'subject_id' => $lane->id,
        ]);
    }

    public function test_inspection_history_can_be_filtered_by_lane_and_result(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:00:00'));

        $user = User::factory()->create();
        $laneA = $this->createOpenEntryLane('CA');
        $laneB = $this->createOpenEntryLane('CB');

        $this->createInspection($user, $laneA, 'AAA111', InspectionResult::Approved);
        $this->createInspection($user, $laneB, 'BBB222', InspectionResult::Rejected);

        $approvedOnLaneA = Inspection::query()
            ->where('lane_id', $laneA->id)
            ->where('result', InspectionResult::Approved)
            ->count();

        $this->assertSame(1, $approvedOnLaneA);
        $this->assertSame(2, Inspection::query()->count());
    }

    private function createOpenEntryLane(string $code): InspectionLane
    {
        return InspectionLane::query()->create([
            'name' => "Carril {$code}",
            'code' => $code,
            'lane_type' => LaneType::FixedEntry,
            'direction' => LaneDirection::Entry,
            'status' => LaneStatus::Open,
            'sort_order' => 1,
        ]);
    }

    private function createInspection(
        User $user,
        InspectionLane $lane,
        string $plate,
        InspectionResult $result,
    ): Inspection {
        $assignment = InspectionAssignment::query()->create([
            'lane_id' => $lane->id,
            'requested_by' => $user->id,
            'hour_slot' => now()->startOfHour(),
            'status' => AssignmentStatus::Completed,
        ]);

        $inspection = Inspection::query()->create([
            'assignment_id' => $assignment->id,
            'lane_id' => $lane->id,
            'user_id' => $user->id,
            'plate' => $plate,
            'result' => $result,
            'inspected_at' => now(),
            'completed_at' => now(),
        ]);

        app(ActivityLogService::class)->log($user, 'inspection_completed', $inspection, [
            'lane_code' => $lane->code,
            'plate' => $plate,
            'result' => $result->value,
        ]);

        return $inspection;
    }
}
