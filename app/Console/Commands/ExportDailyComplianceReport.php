<?php

namespace App\Console\Commands;

use App\Services\InspectionComplianceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class ExportDailyComplianceReport extends Command
{
    protected $signature = 'inspections:export-daily-report
                            {--date= : Fecha Y-m-d (default: hoy)}
                            {--output= : Directorio de salida (default: storage/app/reports)}';

    protected $description = 'Exporta el informe CSV de cumplimiento diario';

    public function handle(InspectionComplianceService $complianceService): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $outputDir = $this->option('output') ?? storage_path('app/reports');
        File::ensureDirectoryExists($outputDir);

        $filename = 'cumplimiento-'.$date->format('Y-m-d').'.csv';
        $path = $outputDir.'/'.$filename;
        $csv = $complianceService->buildDailyReportCsv($date);

        File::put($path, $csv);

        $this->info("Informe guardado: {$path}");

        return self::SUCCESS;
    }
}
