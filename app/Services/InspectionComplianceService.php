<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\ActivityLog;
use App\Models\InspectionAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionComplianceService
{
    public function __construct(
        private InspectionRandomService $randomService,
        private InspectionOperatingHoursService $operatingHours,
    ) {}

    /**
     * @return array{
     *     total_due: int,
     *     completed: int,
     *     missed: int,
     *     cancelled: int,
     *     in_progress: int,
     *     compliance_rate: float,
     *     date: string,
     * }
     */
    public function getDailySummary(?Carbon $date = null): array
    {
        $date ??= now();
        $date = $date->copy()->startOfDay();
        $asOf = $date->isSameDay(now()) ? now() : $date->copy()->endOfDay();
        $breakdown = $this->getDailyBreakdown($date, $asOf);

        $completed = $breakdown->where('status', 'completed')->count();
        $missed = $breakdown->where('status', 'missed')->count();
        $cancelled = $breakdown->where('status', 'cancelled')->count();
        $inProgress = $breakdown->where('status', 'in_progress')->count();
        $totalDue = $breakdown->count();

        $complianceRate = $totalDue > 0
            ? round(($completed / $totalDue) * 100, 1)
            : 100.0;

        return [
            'total_due' => $totalDue,
            'completed' => $completed,
            'missed' => $missed,
            'cancelled' => $cancelled,
            'in_progress' => $inProgress,
            'compliance_rate' => $complianceRate,
            'date' => $date->format('d/m/Y'),
        ];
    }

    /**
     * @return Collection<int, array{
     *     hour_slot: Carbon,
     *     hour_label: string,
     *     status: string,
     *     status_label: string,
     *     lane: ?string,
     *     plate: ?string,
     *     result: ?string,
     *     detail: ?string,
     * }>
     */
    public function getDailyBreakdown(?Carbon $date = null, ?Carbon $asOf = null): Collection
    {
        $asOf ??= now();
        $date ??= $asOf->copy();
        $date = $date->copy()->startOfDay();

        if ($date->isFuture()) {
            return collect();
        }

        $firstHour = $this->operatingHours->firstHourSlotForDay($date);
        $lastHour = $this->operatingHours->lastDueHourSlot($date, $asOf);

        if ($lastHour->lt($firstHour)) {
            return collect();
        }

        $rows = collect();

        for ($hour = $firstHour->copy(); $hour->lte($lastHour); $hour->addHour()) {
            if (! $this->operatingHours->isWithinOperatingHours($hour)) {
                continue;
            }

            $status = $this->getHourStatus($hour, $asOf->copy()->startOfHour());
            $details = $this->getHourDetails($hour, $status);

            $rows->push([
                'hour_slot' => $hour->copy(),
                'hour_label' => sprintf(
                    '%s – %s',
                    $hour->format('H:i'),
                    $hour->copy()->addHour()->format('H:i'),
                ),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'lane' => $details['lane'] ?? null,
                'plate' => $details['plate'] ?? null,
                'result' => $details['result'] ?? null,
                'detail' => $details['detail'] ?? null,
            ]);
        }

        return $rows;
    }

    public function getHourStatus(Carbon $hourSlot, ?Carbon $currentHourSlot = null): string
    {
        $currentHourSlot ??= $this->randomService->currentHourSlot();

        $assignment = InspectionAssignment::query()
            ->where('hour_slot', $hourSlot)
            ->whereIn('status', [
                AssignmentStatus::Completed,
                AssignmentStatus::Pending,
                AssignmentStatus::Cancelled,
            ])
            ->latest('id')
            ->first();

        if ($assignment?->status === AssignmentStatus::Completed) {
            return 'completed';
        }

        if ($assignment?->status === AssignmentStatus::Cancelled) {
            return 'cancelled';
        }

        if ($assignment?->status === AssignmentStatus::Pending) {
            return $hourSlot->equalTo($currentHourSlot) ? 'in_progress' : 'cancelled';
        }

        if ($this->hourMissedWasLogged($hourSlot)) {
            return 'missed';
        }

        if ($hourSlot->lt($currentHourSlot)) {
            return 'missed';
        }

        return 'pending';
    }

    public function hourMissedWasLogged(Carbon $hourSlot): bool
    {
        return ActivityLog::query()
            ->where('action', 'hour_missed')
            ->whereJsonContains('metadata->hour_slot', $hourSlot->toDateTimeString())
            ->exists();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRegenerationsForDate(Carbon $date): Collection
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        return ActivityLog::query()
            ->with('user')
            ->where('action', 'assignment_regenerated')
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ActivityLog $log): array {
                $metadata = is_array($log->metadata) ? $log->metadata : [];

                return [
                    'at' => $log->created_at,
                    'user' => $log->user?->name,
                    'hour_slot' => $metadata['hour_slot'] ?? '—',
                    'previous_lane' => $metadata['previous_lane_code'] ?? '—',
                    'new_lane' => $metadata['new_lane_code'] ?? '—',
                    'reason' => $metadata['reason'] ?? '—',
                ];
            });
    }

    public function exportDailyReportCsv(?Carbon $date = null): StreamedResponse
    {
        $date ??= now();
        $date = $date->copy()->startOfDay();
        $filename = 'cumplimiento-'.$date->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($date): void {
            echo $this->buildDailyReportCsv($date);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function buildDailyReportCsv(?Carbon $date = null): string
    {
        $date ??= now();
        $date = $date->copy()->startOfDay();
        $asOf = $date->isSameDay(now()) ? now() : $date->copy()->endOfDay();
        $breakdown = $this->getDailyBreakdown($date, $asOf);
        $summary = $this->getDailySummary($date);

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($handle, ['Informe de cumplimiento diario', $date->format('d/m/Y')]);
        fputcsv($handle, ['Cumplimiento', $summary['compliance_rate'].'%']);
        fputcsv($handle, ['Completadas', $summary['completed']]);
        fputcsv($handle, ['Sin random', $summary['missed']]);
        fputcsv($handle, ['Canceladas', $summary['cancelled']]);
        fputcsv($handle, []);

        fputcsv($handle, [
            'Franja',
            'Estado',
            'Carril',
            'Placa',
            'Resultado',
            'Detalle',
        ]);

        foreach ($breakdown as $row) {
            fputcsv($handle, [
                $row['hour_label'],
                $row['status_label'],
                $row['lane'] ?? '',
                $row['plate'] ?? '',
                $row['result'] ?? '',
                $row['detail'] ?? '',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }

    /**
     * @return array{lane: ?string, plate: ?string, result: ?string, detail: ?string}
     */
    protected function getHourDetails(Carbon $hourSlot, string $status): array
    {
        $assignment = InspectionAssignment::query()
            ->with(['lane', 'inspection'])
            ->where('hour_slot', $hourSlot)
            ->whereIn('status', [
                AssignmentStatus::Completed,
                AssignmentStatus::Cancelled,
                AssignmentStatus::Pending,
            ])
            ->latest('id')
            ->first();

        $regenerationNote = $this->getRegenerationNote($hourSlot);

        if ($status === 'completed' && $assignment?->inspection) {
            return [
                'lane' => $assignment->lane->code,
                'plate' => $assignment->inspection->plate,
                'result' => $assignment->inspection->result->label(),
                'detail' => $regenerationNote,
            ];
        }

        if ($status === 'cancelled' && $assignment) {
            return [
                'lane' => $assignment->lane->code,
                'detail' => trim(($regenerationNote ? $regenerationNote.' · ' : '').'Random sin inspección a tiempo'),
            ];
        }

        if ($status === 'in_progress' && $assignment) {
            return [
                'lane' => $assignment->lane->code,
                'detail' => $regenerationNote ?? 'Inspección pendiente',
            ];
        }

        if ($status === 'missed') {
            return [
                'detail' => 'No se generó random',
            ];
        }

        return [];
    }

    protected function getRegenerationNote(Carbon $hourSlot): ?string
    {
        $log = ActivityLog::query()
            ->where('action', 'assignment_regenerated')
            ->whereJsonContains('metadata->hour_slot', $hourSlot->toDateTimeString())
            ->latest('id')
            ->first();

        if ($log === null) {
            return null;
        }

        $metadata = is_array($log->metadata) ? $log->metadata : [];

        return sprintf(
            'Regenerado: %s → %s',
            $metadata['previous_lane_code'] ?? '?',
            $metadata['new_lane_code'] ?? '?',
        );
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Completada',
            'missed' => 'Sin random',
            'cancelled' => 'Cancelada',
            'in_progress' => 'En curso',
            default => 'Pendiente',
        };
    }

    /**
     * @return array{
     *     week_start: string,
     *     week_end: string,
     *     total_due: int,
     *     completed: int,
     *     missed: int,
     *     cancelled: int,
     *     compliance_rate: float,
     *     days: Collection<int, array{date: string, label: string, compliance_rate: float, completed: int, total_due: int}>,
     * }
     */
    public function getWeeklySummary(?Carbon $referenceDate = null): array
    {
        $referenceDate ??= now();
        $weekStart = $referenceDate->copy()->startOfWeek();
        $weekEnd = $referenceDate->copy()->endOfWeek();
        $today = now()->startOfDay();

        $days = collect();
        $totals = [
            'total_due' => 0,
            'completed' => 0,
            'missed' => 0,
            'cancelled' => 0,
        ];

        for ($day = $weekStart->copy(); $day->lte($weekEnd); $day->addDay()) {
            if ($day->isFuture()) {
                continue;
            }

            $summary = $this->getDailySummary($day->copy());

            $days->push([
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('D d/m'),
                'compliance_rate' => $summary['compliance_rate'],
                'completed' => $summary['completed'],
                'total_due' => $summary['total_due'],
            ]);

            $totals['total_due'] += $summary['total_due'];
            $totals['completed'] += $summary['completed'];
            $totals['missed'] += $summary['missed'];
            $totals['cancelled'] += $summary['cancelled'];
        }

        $complianceRate = $totals['total_due'] > 0
            ? round(($totals['completed'] / $totals['total_due']) * 100, 1)
            : 100.0;

        return [
            'week_start' => $weekStart->format('d/m/Y'),
            'week_end' => $weekEnd->format('d/m/Y'),
            'total_due' => $totals['total_due'],
            'completed' => $totals['completed'],
            'missed' => $totals['missed'],
            'cancelled' => $totals['cancelled'],
            'compliance_rate' => $complianceRate,
            'days' => $days,
        ];
    }
}
