<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InspectionAssignment extends Model
{
    protected $fillable = [
        'lane_id',
        'requested_by',
        'hour_slot',
        'pending_hour_slot',
        'status',
        'superseded_by_id',
        'regeneration_reason',
        'regenerated_by',
        'regenerated_at',
    ];

    protected function casts(): array
    {
        return [
            'hour_slot' => 'datetime',
            'pending_hour_slot' => 'datetime',
            'status' => AssignmentStatus::class,
            'regenerated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InspectionAssignment $assignment): void {
            $assignment->pending_hour_slot = $assignment->status === AssignmentStatus::Pending
                ? $assignment->hour_slot
                : null;
        });
    }

    public function lane(): BelongsTo
    {
        return $this->belongsTo(InspectionLane::class, 'lane_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function regeneratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regenerated_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function inspection(): HasOne
    {
        return $this->hasOne(Inspection::class, 'assignment_id');
    }

    public function scopeForHour($query, \DateTimeInterface $hourSlot)
    {
        return $query->where('hour_slot', $hourSlot);
    }

    public function scopeActiveForHour($query, \DateTimeInterface $hourSlot)
    {
        return $query
            ->forHour($hourSlot)
            ->where('status', AssignmentStatus::Pending);
    }

    public function scopeEffectiveForHour($query, \DateTimeInterface $hourSlot)
    {
        return $query
            ->forHour($hourSlot)
            ->whereIn('status', [
                AssignmentStatus::Pending,
                AssignmentStatus::Completed,
            ]);
    }
}
