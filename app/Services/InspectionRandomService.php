<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\InspectionResult;
use App\Models\Inspection;
use App\Models\InspectionAssignment;
use App\Models\InspectionLane;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionRandomService
{
    public function __construct(
        private ActivityLogService $activityLog,
    ) {}

    public function currentHourSlot(): Carbon
    {
        return now()->startOfHour();
    }

    public function getActiveAssignment(?Carbon $hourSlot = null): ?InspectionAssignment
    {
        $hourSlot ??= $this->currentHourSlot();

        return InspectionAssignment::query()
            ->with(['lane', 'inspection'])
            ->activeForHour($hourSlot)
            ->latest('id')
            ->first();
    }

    public function getCurrentHourAssignment(?Carbon $hourSlot = null): ?InspectionAssignment
    {
        $hourSlot ??= $this->currentHourSlot();

        return InspectionAssignment::query()
            ->with(['lane', 'inspection'])
            ->effectiveForHour($hourSlot)
            ->latest('id')
            ->first();
    }

    public function getEligibleLanes(): Collection
    {
        return InspectionLane::query()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (InspectionLane $lane) => $lane->isEligibleForRandom());
    }

    public function generateRandom(User $user): InspectionAssignment
    {
        $eligibleLanes = $this->getEligibleLanes();

        if ($eligibleLanes->isEmpty()) {
            throw ValidationException::withMessages([
                'lanes' => 'No hay carriles abiertos en entrada disponibles para el sorteo.',
            ]);
        }

        return DB::transaction(function () use ($user, $eligibleLanes) {
            $hourSlot = $this->currentHourSlot();

            $this->assertHourSlotAvailableForRandom($hourSlot);

            $lane = $eligibleLanes->random();

            $assignment = InspectionAssignment::query()->create([
                'lane_id' => $lane->id,
                'requested_by' => $user->id,
                'hour_slot' => $hourSlot,
                'status' => AssignmentStatus::Pending,
            ]);

            $this->activityLog->log($user, 'assignment_created', $assignment, [
                'lane_code' => $lane->code,
                'lane_name' => $lane->name,
                'hour_slot' => $assignment->hour_slot->toDateTimeString(),
            ]);

            return $assignment->load('lane');
        });
    }

    public function regenerate(User $user, string $reason, ?InspectionAssignment $assignment = null): InspectionAssignment
    {
        if (strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages([
                'regeneration_reason' => 'El motivo debe tener al menos 10 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($user, $reason, $assignment) {
            $hourSlot = $assignment?->hour_slot ?? $this->currentHourSlot();

            $assignment = $this->lockPendingAssignment($assignment, $hourSlot);

            $eligibleLanes = $this->getEligibleLanes()
                ->reject(fn (InspectionLane $lane) => $lane->id === $assignment->lane_id);

            if ($eligibleLanes->isEmpty()) {
                throw ValidationException::withMessages([
                    'lanes' => 'No hay otro carril elegible disponible para regenerar.',
                ]);
            }

            $assignment->load('lane');
            $newLane = $eligibleLanes->random();

            $newAssignment = InspectionAssignment::query()->create([
                'lane_id' => $newLane->id,
                'requested_by' => $user->id,
                'hour_slot' => $assignment->hour_slot,
                'status' => AssignmentStatus::Pending,
            ]);

            $assignment->update([
                'status' => AssignmentStatus::Superseded,
                'superseded_by_id' => $newAssignment->id,
                'regeneration_reason' => trim($reason),
                'regenerated_by' => $user->id,
                'regenerated_at' => now(),
            ]);

            $this->activityLog->log($user, 'assignment_regenerated', $newAssignment, [
                'previous_lane_code' => $assignment->lane->code,
                'new_lane_code' => $newLane->code,
                'reason' => trim($reason),
                'hour_slot' => $newAssignment->hour_slot->toDateTimeString(),
            ]);

            return $newAssignment->load('lane');
        });
    }

    public function registerInspection(
        User $user,
        InspectionAssignment $assignment,
        string $plate,
        InspectionResult $result,
        ?string $comments = null,
    ): Inspection {
        if (strlen(trim($plate)) < 3 || ! preg_match('/^[A-Z0-9-]{3,20}$/', strtoupper(trim($plate)))) {
            throw ValidationException::withMessages([
                'plate' => 'Placa inválida: use 3–20 caracteres alfanuméricos.',
            ]);
        }

        if (in_array($result, [InspectionResult::Rejected, InspectionResult::Conditional], true)
            && strlen(trim($comments ?? '')) < 5) {
            throw ValidationException::withMessages([
                'comments' => 'Los comentarios son obligatorios (mín. 5 caracteres) para Rechazado o Condicional.',
            ]);
        }

        return DB::transaction(function () use ($user, $assignment, $plate, $result, $comments) {
            $assignment = InspectionAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status !== AssignmentStatus::Pending) {
                throw ValidationException::withMessages([
                    'assignment' => 'Esta asignación ya no está pendiente.',
                ]);
            }

            if ($assignment->inspection()->exists()) {
                throw ValidationException::withMessages([
                    'inspection' => 'Esta asignación ya tiene una inspección registrada.',
                ]);
            }

            $inspectedAt = now();

            $inspection = Inspection::query()->create([
                'assignment_id' => $assignment->id,
                'lane_id' => $assignment->lane_id,
                'user_id' => $user->id,
                'plate' => strtoupper(trim($plate)),
                'result' => $result,
                'comments' => $comments,
                'inspected_at' => $inspectedAt,
                'completed_at' => $inspectedAt,
            ]);

            $assignment->update([
                'status' => AssignmentStatus::Completed,
            ]);

            $assignment->load('lane');

            $this->activityLog->log($user, 'inspection_completed', $inspection, [
                'lane_code' => $assignment->lane->code,
                'plate' => $inspection->plate,
                'result' => $result->value,
            ]);

            return $inspection->load(['lane', 'assignment']);
        });
    }

    protected function assertHourSlotAvailableForRandom(Carbon $hourSlot): void
    {
        if (InspectionAssignment::query()->activeForHour($hourSlot)->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'assignment' => 'Ya existe una inspección pendiente para esta hora.',
            ]);
        }

        if (InspectionAssignment::query()
            ->where('hour_slot', $hourSlot)
            ->where('status', AssignmentStatus::Completed)
            ->lockForUpdate()
            ->exists()) {
            throw ValidationException::withMessages([
                'assignment' => 'Esta hora ya tiene una inspección completada.',
            ]);
        }
    }

    protected function lockPendingAssignment(?InspectionAssignment $assignment, Carbon $hourSlot): InspectionAssignment
    {
        if ($assignment !== null) {
            $assignment = InspectionAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->first();
        }

        if ($assignment === null) {
            $assignment = InspectionAssignment::query()
                ->activeForHour($hourSlot)
                ->lockForUpdate()
                ->latest('id')
                ->first();
        }

        if ($assignment === null || $assignment->status !== AssignmentStatus::Pending) {
            throw ValidationException::withMessages([
                'assignment' => 'No hay una asignación activa para regenerar.',
            ]);
        }

        return $assignment;
    }
}
