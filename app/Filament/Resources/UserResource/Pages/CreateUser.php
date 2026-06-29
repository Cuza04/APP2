<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Usuario creado';
    }

    protected function afterCreate(): void
    {
        UserResource::logUserChange($this->record, 'user_created');
    }
}
