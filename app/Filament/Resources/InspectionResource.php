<?php

namespace App\Filament\Resources;

use App\Enums\InspectionResult;
use App\Filament\Resources\InspectionResource\Pages;
use App\Models\Inspection;
use App\Models\InspectionLane;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Historial de inspecciones';

    protected static ?string $modelLabel = 'inspección';

    protected static ?string $pluralModelLabel = 'Historial de inspecciones';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['lane', 'user', 'assignment']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inspected_at')
                    ->label('Fecha inspección')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignment.hour_slot')
                    ->label('Franja horaria')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null) {
                            return '—';
                        }

                        $start = \Illuminate\Support\Carbon::parse($state);

                        return sprintf(
                            '%s – %s',
                            $start->format('d/m/Y H:i'),
                            $start->copy()->addHour()->format('H:i'),
                        );
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('lane.name')
                    ->label('Carril')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lane.code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plate')
                    ->label('Placa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('result')
                    ->label('Resultado')
                    ->badge()
                    ->formatStateUsing(fn (InspectionResult $state): string => $state->label())
                    ->color(fn (InspectionResult $state): string => match ($state) {
                        InspectionResult::Approved => 'success',
                        InspectionResult::Rejected => 'danger',
                        InspectionResult::Conditional => 'warning',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Operador')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->label('Comentarios')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->defaultSort('inspected_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('inspected_at')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('inspected_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('inspected_at', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('lane_id')
                    ->label('Carril')
                    ->options(fn (): array => InspectionLane::query()
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all()),
                Tables\Filters\SelectFilter::make('result')
                    ->label('Resultado')
                    ->options(collect(InspectionResult::cases())->mapWithKeys(
                        fn (InspectionResult $case) => [$case->value => $case->label()]
                    )),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspections::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
