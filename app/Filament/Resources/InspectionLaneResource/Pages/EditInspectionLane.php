<?php

namespace App\Filament\Resources\InspectionLaneResource\Pages;

use App\Filament\Resources\InspectionLaneResource;
use Filament\Resources\Pages\EditRecord;

class EditInspectionLane extends EditRecord
{
    protected static string $resource = InspectionLaneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make()
                ->before(function (\Filament\Actions\DeleteAction $action): void {
                    if ($this->record->inspections()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se puede eliminar')
                            ->body('Este carril tiene inspecciones registradas.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        $changes = collect($this->record->getChanges())
            ->except(['updated_at'])
            ->all();

        if ($changes === []) {
            return;
        }

        InspectionLaneResource::logLaneChange($this->record, 'lane_updated', [
            'changes' => $changes,
        ]);
    }
}
