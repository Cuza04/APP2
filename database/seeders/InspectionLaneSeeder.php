<?php

namespace Database\Seeders;

use App\Enums\LaneDirection;
use App\Enums\LaneStatus;
use App\Enums\LaneType;
use App\Models\InspectionLane;
use Illuminate\Database\Seeder;

class InspectionLaneSeeder extends Seeder
{
    public function run(): void
    {
        $lanes = [
            ['name' => 'Carril 1', 'code' => 'C1', 'lane_type' => LaneType::FixedEntry, 'direction' => LaneDirection::Entry, 'sort_order' => 1],
            ['name' => 'Carril 2', 'code' => 'C2', 'lane_type' => LaneType::FixedEntry, 'direction' => LaneDirection::Entry, 'sort_order' => 2],
            ['name' => 'Carril 3', 'code' => 'C3', 'lane_type' => LaneType::FixedEntry, 'direction' => LaneDirection::Entry, 'sort_order' => 3],
            ['name' => 'Carril 4', 'code' => 'C4', 'lane_type' => LaneType::FixedEntry, 'direction' => LaneDirection::Entry, 'sort_order' => 4],
            ['name' => 'Carril 5', 'code' => 'C5', 'lane_type' => LaneType::FixedEntry, 'direction' => LaneDirection::Entry, 'sort_order' => 5],
            ['name' => 'Carril 6', 'code' => 'C6', 'lane_type' => LaneType::Flexible, 'direction' => LaneDirection::Entry, 'sort_order' => 6],
            ['name' => 'Carril 7', 'code' => 'C7', 'lane_type' => LaneType::Flexible, 'direction' => LaneDirection::Entry, 'sort_order' => 7],
        ];

        foreach ($lanes as $lane) {
            InspectionLane::query()->updateOrCreate(
                ['code' => $lane['code']],
                [
                    ...$lane,
                    'status' => LaneStatus::Open,
                ],
            );
        }
    }
}
