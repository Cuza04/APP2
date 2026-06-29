<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Historial';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'Historial de movimientos';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'assignment_created' => 'Random generado',
                        'assignment_regenerated' => 'Random regenerado',
                        'inspection_completed' => 'Inspección registrada',
                        'lane_created' => 'Carril creado',
                        'lane_updated' => 'Carril actualizado',
                        'lane_status_changed' => 'Estado de carril cambiado',
                        'user_created' => 'Usuario creado',
                        'user_updated' => 'Usuario actualizado',
                        'user_deactivated' => 'Usuario desactivado',
                        'user_activated' => 'Usuario activado',
                        'assignment_cancelled' => 'Asignación cancelada',
                        'hour_missed' => 'Hora sin random',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Detalle')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        if (is_string($state)) {
                            $state = json_decode($state, true);
                        }

                        if (! is_array($state)) {
                            return '—';
                        }

                        return collect($state)
                            ->map(fn ($value, $key) => "{$key}: {$value}")
                            ->implode(' · ');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Acción')
                    ->options([
                        'assignment_created' => 'Random generado',
                        'assignment_regenerated' => 'Random regenerado',
                        'inspection_completed' => 'Inspección registrada',
                        'assignment_cancelled' => 'Asignación cancelada',
                        'hour_missed' => 'Hora sin random',
                        'lane_status_changed' => 'Estado de carril cambiado',
                        'user_created' => 'Usuario creado',
                        'user_updated' => 'Usuario actualizado',
                        'user_deactivated' => 'Usuario desactivado',
                        'user_activated' => 'Usuario activado',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('Fecha')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
