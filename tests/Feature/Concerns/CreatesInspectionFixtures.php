<?php

namespace Tests\Feature\Concerns;

use App\Enums\LaneDirection;
use App\Enums\LaneStatus;
use App\Enums\LaneType;
use App\Models\InspectionLane;

trait CreatesInspectionFixtures
{
    protected function createOpenEntryLane(string $code = 'C1'): InspectionLane
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
}
