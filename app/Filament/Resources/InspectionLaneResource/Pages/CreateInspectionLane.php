<?php

namespace App\Filament\Resources\InspectionLaneResource\Pages;

use App\Filament\Resources\InspectionLaneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInspectionLane extends CreateRecord
{
    protected static string $resource = InspectionLaneResource::class;

    protected function afterCreate(): void
    {
        InspectionLaneResource::logLaneChange($this->record, 'lane_created', [
            'status' => $this->record->status->value,
            'direction' => $this->record->direction->value,
        ]);
    }
}
