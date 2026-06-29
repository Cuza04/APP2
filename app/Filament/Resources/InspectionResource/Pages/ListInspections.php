<?php

namespace App\Filament\Resources\InspectionResource\Pages;

use App\Filament\Resources\InspectionResource;
use App\Models\Inspection;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInspections extends ListRecords
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'inspecciones-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Fecha inspección',
                'Franja inicio',
                'Franja fin',
                'Carril',
                'Código carril',
                'Placa',
                'Resultado',
                'Operador',
                'Comentarios',
            ]);

            $this->getFilteredTableQuery()
                ->with(['lane', 'user', 'assignment'])
                ->orderByDesc('inspected_at')
                ->chunk(200, function ($inspections) use ($handle): void {
                    /** @var Inspection $inspection */
                    foreach ($inspections as $inspection) {
                        $hourSlot = $inspection->assignment?->hour_slot;

                        fputcsv($handle, [
                            $inspection->inspected_at?->format('d/m/Y H:i') ?? '',
                            $hourSlot?->format('d/m/Y H:i') ?? '',
                            $hourSlot?->copy()->addHour()->format('d/m/Y H:i') ?? '',
                            $inspection->lane?->name ?? '',
                            $inspection->lane?->code ?? '',
                            $inspection->plate,
                            $inspection->result->label(),
                            $inspection->user?->name ?? '',
                            $inspection->comments ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
