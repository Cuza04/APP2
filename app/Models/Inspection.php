<?php

namespace App\Models;

use App\Enums\InspectionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspection extends Model
{
    protected $fillable = [
        'assignment_id',
        'lane_id',
        'user_id',
        'plate',
        'result',
        'comments',
        'inspected_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => InspectionResult::class,
            'inspected_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InspectionAssignment::class, 'assignment_id');
    }

    public function lane(): BelongsTo
    {
        return $this->belongsTo(InspectionLane::class, 'lane_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
