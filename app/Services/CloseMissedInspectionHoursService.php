<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\ActivityLog;
use App\Models\InspectionAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

class CloseMissedInspectionHoursService
{
    public function __construct(
        private ActivityLogService $activityLog,
        private InspectionOperatingHoursService $operatingHours,
    ) {}

    public function closeStalePendingAssignments(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $currentHourSlot = $asOf->copy()->startOfHour();

        $assignments = InspectionAssignment::query()
            ->with(['requestedBy', 'lane'])
            ->where('status', AssignmentStatus::Pending)
            ->where('hour_slot', '<', $currentHourSlot)
            ->get();

        $closed = 0;

        foreach ($assignments as $assignment) {
            $assignment->update([
                'status' => AssignmentStatus::Cancelled,
            ]);

            $this->activityLog->log(
                $assignment->requestedBy,
                'assignment_cancelled',
                $assignment,
                [
                    'reason' => 'hour_expired_without_inspection',
                    'hour_slot' => $assignment->hour_slot->toDateTimeString(),
                    'lane_code' => $assignment->lane->code,
                    'automated' => true,
                ],
            );

            $closed++;
        }

        return $closed;
    }

    public function logMissedHoursWithoutRandom(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $currentHourSlot = $asOf->copy()->startOfHour();
        $dayStart = $this->operatingHours->firstHourSlotForDay($asOf);
        $lastClosedHour = $currentHourSlot->copy()->subHour();

        if ($lastClosedHour->gt($this->operatingHours->lastOperatingHourSlotForDay($asOf))) {
            $lastClosedHour = $this->operatingHours->lastOperatingHourSlotForDay($asOf);
        }
        $user = User::query()->first();

        if ($user === null) {
            return 0;
        }

        $logged = 0;

        for ($hour = $dayStart->copy(); $hour->lte($lastClosedHour); $hour->addHour()) {
            if (! $this->operatingHours->isWithinOperatingHours($hour)) {
                continue;
            }

            if ($this->hourHasComplianceRecord($hour)) {
                continue;
            }

            if ($this->hourMissedAlreadyLogged($hour)) {
                continue;
            }

            $this->activityLog->log($user, 'hour_missed', null, [
                'hour_slot' => $hour->toDateTimeString(),
                'reason' => 'no_random_generated',
                'automated' => true,
            ]);

            $logged++;
        }

        return $logged;
    }

    public function run(?Carbon $asOf = null): array
    {
        return [
            'cancelled_assignments' => $this->closeStalePendingAssignments($asOf),
            'logged_missed_hours' => $this->logMissedHoursWithoutRandom($asOf),
        ];
    }

    protected function hourHasComplianceRecord(Carbon $hourSlot): bool
    {
        return InspectionAssignment::query()
            ->where('hour_slot', $hourSlot)
            ->whereIn('status', [
                AssignmentStatus::Pending,
                AssignmentStatus::Completed,
                AssignmentStatus::Cancelled,
            ])
            ->exists();
    }

    protected function hourMissedAlreadyLogged(Carbon $hourSlot): bool
    {
        return ActivityLog::query()
            ->where('action', 'hour_missed')
            ->whereJsonContains('metadata->hour_slot', $hourSlot->toDateTimeString())
            ->exists();
    }
}
