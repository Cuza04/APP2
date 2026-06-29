<?php

namespace Tests\Feature;

use App\Filament\Pages\InspectionControl;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class InspectionControlUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Bogota']);
        date_default_timezone_set('America/Bogota');
    }

    public function test_poll_reminders_updates_session_when_hour_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 10:45:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        session(['inspection_hour_slot' => '2026-06-26 10:00:00']);

        Carbon::setTestNow(Carbon::parse('2026-06-26 11:05:00', 'America/Bogota'));

        Livewire::test(InspectionControl::class)
            ->call('pollReminders')
            ->assertSet('lastToastReminderKey', '2026-06-26 11:00:00-info');

        $this->assertSame('2026-06-26 11:00:00', session('inspection_hour_slot'));
    }

    public function test_reminder_banner_message_reflects_warning_threshold(): void
    {
        config(['inspection.reminder_warning_minute' => 12]);

        Carbon::setTestNow(Carbon::parse('2026-06-26 10:20:00', 'America/Bogota'));

        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(InspectionControl::class)
            ->assertSet('assignmentId', null)
            ->tap(function ($component): void {
                $message = $component->instance()->reminderBannerMessage();
                $this->assertNotNull($message);
                $this->assertStringContainsString('12 minutos', $message);
            });
    }
}
