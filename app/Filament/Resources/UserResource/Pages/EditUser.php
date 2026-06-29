<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Usuario actualizado';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (UserResource::cannotChangeActiveStatus($this->record) && array_key_exists('is_active', $data)) {
            $data['is_active'] = $this->record->is_active;
        }

        if (($data['is_active'] ?? true) === false && $this->record->is_active) {
            $reason = UserResource::cannotChangeActiveStatusReason($this->record);

            if ($reason !== null) {
                Notification::make()
                    ->title('No se puede desactivar')
                    ->body($reason)
                    ->danger()
                    ->send();

                $data['is_active'] = true;
            }
        }

        if (filled($data['password'] ?? null)) {
            $data['password_changed_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        UserResource::logUserChange($this->record, 'user_updated');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $wasActive = $record->is_active;

        $record = parent::handleRecordUpdate($record, $data);

        if ($wasActive && ! $record->is_active) {
            DB::table('sessions')->where('user_id', $record->id)->delete();
        }

        return $record;
    }
}
