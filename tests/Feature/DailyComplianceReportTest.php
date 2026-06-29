<?php

namespace Tests\Feature;

use App\Enums\InspectionResult;
use App\Models\User;
use App\Services\InspectionComplianceService;
use App\Services\InspectionRandomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesInspectionFixtures;
use Tests\TestCase;

class DailyComplianceReportTest extends TestCase
{
    use CreatesInspectionFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
    }

    public function test_daily_breakdown_marks_completed_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:30:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->createOpenEntryLane('C1');
        $randomService = app(InspectionRandomService::class);

        Carbon::setTestNow(Carbon::parse('2026-06-26 09:10:00', 'America/Bogota'));
        $assignment = $randomService->generateRandom($user);
        $randomService->registerInspection($user, $assignment, 'ABC123', InspectionResult::Approved);

        Carbon::setTestNow(Carbon::parse('2026-06-26 10:30:00', 'America/Bogota'));

        $breakdown = app(InspectionComplianceService::class)->getDailyBreakdown(
            Carbon::parse('2026-06-26'),
            now(),
        );

        $nineAm = $breakdown->first(fn (array $row) => $row['hour_label'] === '09:00 – 10:00');

        $this->assertNotNull($nineAm);
        $this->assertSame('completed', $nineAm['status']);
        $this->assertSame('ABC123', $nineAm['plate']);
    }

    public function test_build_daily_report_csv_contains_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 08:00:00', 'America/Bogota'));

        User::factory()->create();

        $csv = app(InspectionComplianceService::class)->buildDailyReportCsv(now());

        $this->assertStringContainsString('Informe de cumplimiento diario', $csv);
        $this->assertStringContainsString('Franja', $csv);
    }

    public function test_weekly_summary_aggregates_completed_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 11:30:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->createOpenEntryLane('C1');
        $randomService = app(InspectionRandomService::class);

        Carbon::setTestNow(Carbon::parse('2026-06-26 09:10:00', 'America/Bogota'));
        $assignment = $randomService->generateRandom($user);
        $randomService->registerInspection($user, $assignment, 'ABC123', InspectionResult::Approved);

        Carbon::setTestNow(Carbon::parse('2026-06-26 11:30:00', 'America/Bogota'));

        $weekly = app(InspectionComplianceService::class)->getWeeklySummary(now());

        $this->assertGreaterThan(0, $weekly['completed']);
        $this->assertGreaterThan(0, $weekly['total_due']);
        $this->assertNotEmpty($weekly['days']);
    }

    public function test_export_daily_report_command_writes_file(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00', 'America/Bogota'));

        User::factory()->create();

        $outputDir = storage_path('framework/testing/reports');

        $this->artisan('inspections:export-daily-report', [
            '--output' => $outputDir,
        ])->assertSuccessful();

        $this->assertFileExists($outputDir.'/cumplimiento-2026-06-26.csv');
    }
}
