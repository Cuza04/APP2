<?php

namespace App\Filament\Widgets;

use App\Services\InspectionComplianceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class DailyComplianceWidget extends BaseWidget
{
    protected static ?int $sort = -3;

    protected static ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    #[On('inspection-control-updated')]
    public function refreshComplianceStats(): void
    {
        $this->cachedStats = null;
    }

    protected function getCachedStats(): array
    {
        return $this->cachedStats ??= $this->getStats();
    }

    protected function getStats(): array
    {
        $summary = app(InspectionComplianceService::class)->getDailySummary();

        $rateColor = match (true) {
            $summary['compliance_rate'] >= 95 => 'success',
            $summary['compliance_rate'] >= 80 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Cumplimiento hoy', $summary['compliance_rate'].'%')
                ->description("{$summary['completed']} de {$summary['total_due']} horas completadas · {$summary['date']}")
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($rateColor),
            Stat::make('Completadas', (string) $summary['completed'])
                ->description('Inspecciones registradas')
                ->color('success'),
            Stat::make('Sin random', (string) $summary['missed'])
                ->description('Horas sin sorteo')
                ->color($summary['missed'] > 0 ? 'danger' : 'gray'),
            Stat::make('Canceladas', (string) $summary['cancelled'])
                ->description('Random sin inspección a tiempo')
                ->color($summary['cancelled'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
