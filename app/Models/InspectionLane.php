<?php

namespace App\Models;

use App\Enums\LaneDirection;
use App\Enums\LaneStatus;
use App\Enums\LaneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionLane extends Model
{
    protected $fillable = [
        'name',
        'code',
        'lane_type',
        'direction',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lane_type' => LaneType::class,
            'direction' => LaneDirection::class,
            'status' => LaneStatus::class,
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(InspectionAssignment::class, 'lane_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'lane_id');
    }

    public function isEligibleForRandom(): bool
    {
        return $this->status === LaneStatus::Open
            && $this->direction === LaneDirection::Entry;
    }
}
