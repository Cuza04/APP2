<?php

namespace App\Filament\Pages\Auth;

use DiogoGPinto\AuthUIEnhancer\Pages\Auth\Concerns\HasCustomLayout;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    use HasCustomLayout;

    protected static string $view = 'filament.pages.auth.login';

    public function hasLogo(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Acceso · '.config('app.name');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Iniciar sesión';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Puesto de monitoreo · Control de inspecciones en carril';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Correo electrónico')
            ->email()
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->placeholder('usuario@terminal.com')
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Mantener sesión en este equipo');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Entrar al panel')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->submit('authenticate');
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $user = User::query()->where('email', $data['email'])->first();

        if ($user instanceof User
            && ! $user->is_active
            && Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'data.email' => 'Esta cuenta está desactivada. Contacte al administrador del puesto.',
            ]);
        }

        return parent::authenticate();
    }
}
