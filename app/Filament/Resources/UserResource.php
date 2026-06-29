<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\ActivityLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios del puesto';

    protected static ?int $navigationSort = 9;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rule(Password::min(config('inspection.min_password_length', 8)))
                    ->maxLength(255)
                    ->helperText(fn (string $operation): ?string => match (true) {
                        $operation === 'edit' => 'Dejar en blanco para mantener la contraseña actual. Si la cambias, el usuario deberá definir una nueva en su próximo acceso.',
                        default => 'Mínimo '.config('inspection.min_password_length', 8).' caracteres. El usuario deberá cambiarla al primer inicio de sesión.',
                    }),
                Forms\Components\Select::make('role')
                    ->label('Rol')
                    ->options(collect(UserRole::cases())->mapWithKeys(
                        fn (UserRole $case) => [$case->value => $case->label()]
                    ))
                    ->default(UserRole::Operator->value)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user()))
                    ->helperText(fn (?User $record): ?string => $record !== null && $record->is(auth()->user())
                        ? 'No puedes cambiar tu propio rol.'
                        : null),
                Forms\Components\Toggle::make('is_active')
                    ->label('Cuenta activa')
                    ->default(true)
                    ->helperText('Las cuentas inactivas no pueden iniciar sesión.')
                    ->disabled(fn (?User $record): bool => $record !== null && self::cannotChangeActiveStatus($record))
                    ->dehydrated(fn (?User $record): bool => $record === null || ! self::cannotChangeActiveStatus($record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Admin => 'warning',
                        UserRole::Operator => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\Action::make('deactivate')
                    ->label('Desactivar')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar usuario')
                    ->modalDescription(fn (User $record): string => "¿Desactivar la cuenta de {$record->name}? No podrá iniciar sesión.")
                    ->visible(fn (User $record): bool => $record->is_active && ! self::cannotChangeActiveStatus($record))
                    ->action(fn (User $record) => self::setActiveStatus($record, false)),
                Tables\Actions\Action::make('activate')
                    ->label('Activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->is_active)
                    ->action(fn (User $record) => self::setActiveStatus($record, true)),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function setActiveStatus(User $user, bool $isActive): void
    {
        if (! auth()->user()?->isAdmin()) {
            Notification::make()
                ->title('Sin permiso')
                ->body('Solo un administrador puede activar o desactivar cuentas.')
                ->danger()
                ->send();

            return;
        }

        if ($isActive === $user->is_active) {
            return;
        }

        if (! $isActive && self::cannotChangeActiveStatus($user)) {
            Notification::make()
                ->title('No se puede desactivar')
                ->body(self::cannotChangeActiveStatusReason($user))
                ->danger()
                ->send();

            return;
        }

        $user->update(['is_active' => $isActive]);

        if (! $isActive) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        self::logUserChange($user, $isActive ? 'user_activated' : 'user_deactivated');

        Notification::make()
            ->title($isActive ? 'Usuario activado' : 'Usuario desactivado')
            ->body("{$user->name} ({$user->email})")
            ->success()
            ->send();
    }

    public static function logUserChange(User $user, string $action, array $metadata = []): void
    {
        app(ActivityLogService::class)->log(auth()->user(), $action, $user, [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'is_active' => $user->is_active,
            ...$metadata,
        ]);
    }

    public static function cannotChangeActiveStatus(User $user): bool
    {
        return self::cannotChangeActiveStatusReason($user) !== null;
    }

    public static function cannotChangeActiveStatusReason(User $user): ?string
    {
        if ($user->is(auth()->user())) {
            return 'No puedes desactivar tu propia cuenta.';
        }

        if ($user->is_active && User::query()->where('is_active', true)->whereKeyNot($user->id)->doesntExist()) {
            return 'Debe quedar al menos un usuario activo en el sistema.';
        }

        return null;
    }
}
