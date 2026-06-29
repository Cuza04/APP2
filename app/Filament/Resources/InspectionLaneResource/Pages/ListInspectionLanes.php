<?php

namespace App\Filament\Resources\InspectionLaneResource\Pages;

use App\Filament\Resources\InspectionLaneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInspectionLanes extends ListRecords
{
    protected static string $resource = InspectionLaneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
