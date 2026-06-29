<?php

namespace App\Filament\Resources;

use App\Enums\LaneDirection;
use App\Enums\LaneStatus;
use App\Enums\LaneType;
use App\Filament\Resources\InspectionLaneResource\Pages;
use App\Models\InspectionLane;
use App\Services\ActivityLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InspectionLaneResource extends Resource
{
    protected static ?string $model = InspectionLane::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Carriles';

    protected static ?string $modelLabel = 'carril';

    protected static ?string $pluralModelLabel = 'Carriles';

    protected static ?int $navigationSort = 8;

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
        return auth()->user()?->isAdmin() && $record->inspections()->doesntExist();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->helperText('Identificador corto, ej. C1'),
                Forms\Components\Select::make('lane_type')
                    ->label('Tipo')
                    ->options(collect(LaneType::cases())->mapWithKeys(
                        fn (LaneType $case) => [$case->value => $case->label()]
                    ))
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('direction')
                    ->label('Dirección')
                    ->options(collect(LaneDirection::cases())->mapWithKeys(
                        fn (LaneDirection $case) => [$case->value => $case->label()]
                    ))
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options(collect(LaneStatus::cases())->mapWithKeys(
                        fn (LaneStatus $case) => [$case->value => $case->label()]
                    ))
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255)
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lane_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (LaneType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('direction')
                    ->label('Dirección')
                    ->badge()
                    ->formatStateUsing(fn (LaneDirection $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (LaneStatus $state): string => $state->label())
                    ->color(fn (LaneStatus $state): string => match ($state) {
                        LaneStatus::Open => 'success',
                        LaneStatus::Closed => 'gray',
                        LaneStatus::Maintenance => 'warning',
                    }),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(LaneStatus::cases())->mapWithKeys(
                        fn (LaneStatus $case) => [$case->value => $case->label()]
                    )),
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Dirección')
                    ->options(collect(LaneDirection::cases())->mapWithKeys(
                        fn (LaneDirection $case) => [$case->value => $case->label()]
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('markOpen')
                    ->label('Abrir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (InspectionLane $record): bool => auth()->user()?->isAdmin() && $record->status !== LaneStatus::Open)
                    ->action(fn (InspectionLane $record) => self::updateLaneStatus($record, LaneStatus::Open)),
                Tables\Actions\Action::make('markClosed')
                    ->label('Cerrar')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (InspectionLane $record): bool => auth()->user()?->isAdmin() && $record->status !== LaneStatus::Closed)
                    ->action(fn (InspectionLane $record) => self::updateLaneStatus($record, LaneStatus::Closed)),
                Tables\Actions\Action::make('markMaintenance')
                    ->label('Mantenimiento')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (InspectionLane $record): bool => auth()->user()?->isAdmin() && $record->status !== LaneStatus::Maintenance)
                    ->action(fn (InspectionLane $record) => self::updateLaneStatus($record, LaneStatus::Maintenance)),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->before(function (Tables\Actions\DeleteAction $action, InspectionLane $record): void {
                        if ($record->inspections()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar')
                                ->body('Este carril tiene inspecciones registradas.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspectionLanes::route('/'),
            'create' => Pages\CreateInspectionLane::route('/create'),
            'edit' => Pages\EditInspectionLane::route('/{record}/edit'),
        ];
    }

    public static function logLaneChange(InspectionLane $lane, string $action, array $metadata = []): void
    {
        app(ActivityLogService::class)->log(auth()->user(), $action, $lane, [
            'lane_code' => $lane->code,
            'lane_name' => $lane->name,
            ...$metadata,
        ]);
    }

    public static function updateLaneStatus(InspectionLane $lane, LaneStatus $status): void
    {
        if (! auth()->user()?->isAdmin()) {
            Notification::make()
                ->title('Sin permiso')
                ->body('Solo un administrador puede abrir, cerrar o poner carriles en mantenimiento.')
                ->danger()
                ->send();

            return;
        }

        $previous = $lane->status;

        if ($previous === $status) {
            return;
        }

        $lane->update(['status' => $status]);

        self::logLaneChange($lane, 'lane_status_changed', [
            'previous_status' => $previous->value,
            'new_status' => $status->value,
        ]);

        Notification::make()
            ->title('Estado actualizado')
            ->body("{$lane->name} ({$lane->code}): {$status->label()}")
            ->success()
            ->send();
    }
}
