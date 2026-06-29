<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class EditProfile extends BaseEditProfile
{
    protected bool $completedRequiredPasswordChange = false;

    public function mount(): void
    {
        parent::mount();

        if ($this->getUser()->mustChangePassword()) {
            Notification::make()
                ->title('Debes cambiar tu contraseña')
                ->body('Es tu primer acceso o un administrador restableció tu clave. Elige una contraseña nueva antes de continuar.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function getHeading(): string|Htmlable
    {
        if ($this->getUser()->mustChangePassword()) {
            return 'Cambiar contraseña';
        }

        return parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->getUser()->mustChangePassword()) {
            return 'Mínimo '.config('inspection.min_password_length', 8).' caracteres.';
        }

        return null;
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Nueva contraseña')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::min(config('inspection.min_password_length', 8)))
            ->required(fn (): bool => $this->getUser()->mustChangePassword())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Confirmar contraseña')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required(fn (): bool => $this->getUser()->mustChangePassword())
            ->visible(fn ($get): bool => filled($get('password')) || $this->getUser()->mustChangePassword())
            ->dehydrated(false);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $passwordChanged = array_key_exists('password', $data);

        $record = parent::handleRecordUpdate($record, $data);

        if ($passwordChanged) {
            $record->forceFill(['password_changed_at' => now()])->save();
            $this->completedRequiredPasswordChange = true;
        }

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        if ($this->completedRequiredPasswordChange) {
            return Filament::getHomeUrl() ?? Filament::getUrl();
        }

        return null;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        if ($this->completedRequiredPasswordChange) {
            return 'Contraseña actualizada. Ya puedes usar el panel.';
        }

        return parent::getSavedNotificationTitle();
    }
}
