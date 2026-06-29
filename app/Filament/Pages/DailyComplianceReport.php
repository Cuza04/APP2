<?php

namespace App\Filament\Pages;

use App\Services\InspectionComplianceService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyComplianceReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Cumplimiento';

    protected static ?string $title = 'Cumplimiento diario';

    protected static ?string $slug = 'daily-compliance';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.daily-compliance-report';

    public ?string $date = null;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function getSummaryProperty(): array
    {
        return app(InspectionComplianceService::class)->getDailySummary(
            Carbon::parse($this->date),
        );
    }

    public function getBreakdownProperty(): \Illuminate\Support\Collection
    {
        return app(InspectionComplianceService::class)->getDailyBreakdown(
            Carbon::parse($this->date),
        );
    }

    public function getRegenerationsProperty(): \Illuminate\Support\Collection
    {
        return app(InspectionComplianceService::class)->getRegenerationsForDate(
            Carbon::parse($this->date),
        );
    }

    public function getWeeklySummaryProperty(): array
    {
        return app(InspectionComplianceService::class)->getWeeklySummary(
            Carbon::parse($this->date),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Exportar informe CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => app(InspectionComplianceService::class)
                    ->exportDailyReportCsv(Carbon::parse($this->date))),
        ];
    }
}
