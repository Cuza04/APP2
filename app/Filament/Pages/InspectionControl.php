<?php

namespace App\Filament\Pages;

use App\Enums\InspectionResult;
use App\Models\InspectionAssignment;
use App\Models\User;
use App\Services\InspectionComplianceService;
use App\Services\InspectionRandomService;
use App\Services\InspectionReminderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class InspectionControl extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.inspection-control';

    protected static ?string $navigationLabel = 'Panel principal';

    protected static ?string $title = 'Control de inspecciones';

    protected static ?string $slug = 'inspection-control';

    protected static string $routePath = '/';

    protected static ?int $navigationSort = -10;

    public ?int $assignmentId = null;

    public ?array $inspectionData = [];

    public ?string $lastToastReminderKey = null;

    public int $uiRevision = 0;

    public int $kpiRevision = 0;

    public function mount(InspectionRandomService $service): void
    {
        session(['inspection_hour_slot' => $service->currentHourSlot()->toDateTimeString()]);
        $this->refreshAssignment($service);
        $this->resetInspectionForm();
        $this->checkLiveReminder(app(InspectionReminderService::class), $service);
    }

    public function pollReminders(
        InspectionRandomService $randomService,
        InspectionReminderService $reminderService,
    ): void {
        $currentHourSlot = $randomService->currentHourSlot()->toDateTimeString();

        if (session('inspection_hour_slot') !== $currentHourSlot) {
            session(['inspection_hour_slot' => $currentHourSlot]);
            $this->refreshAssignment($randomService);
            $this->lastToastReminderKey = null;
        }

        $this->checkLiveReminder($reminderService, $randomService);
        $this->refreshKpis();
    }

    /**
     * @return array{
     *     total_due: int,
     *     completed: int,
     *     missed: int,
     *     cancelled: int,
     *     in_progress: int,
     *     compliance_rate: float,
     *     date: string,
     * }
     */
    public function dailySummary(): array
    {
        return app(InspectionComplianceService::class)->getDailySummary();
    }

    public function complianceRateTone(float $complianceRate): string
    {
        return match (true) {
            $complianceRate >= 95 => 'border-orange-400 bg-orange-50',
            $complianceRate >= 80 => 'border-amber-400 bg-amber-50',
            default => 'border-red-400 bg-red-50',
        };
    }

    public function missedTone(int $missed): string
    {
        return $missed > 0
            ? 'border-red-400 bg-red-50'
            : 'border-slate-300 bg-slate-50';
    }

    public function dailyStatusLabel(float $complianceRate): string
    {
        return match (true) {
            $complianceRate >= 95 => 'En meta',
            $complianceRate >= 80 => 'Atención',
            default => 'Bajo cumplimiento',
        };
    }

    public function refreshKpis(): void
    {
        $this->kpiRevision++;
    }

    public function eligibleLaneCount(): int
    {
        return app(InspectionRandomService::class)->getEligibleLanes()->count();
    }

    protected function authenticatedUser(): User
    {
        $user = auth()->guard()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    public function resolveReminderLevel(): ?string
    {
        $reminderService = app(InspectionReminderService::class);

        if (! $reminderService->needsRandomReminder()) {
            return null;
        }

        return $reminderService->getReminderLevel();
    }

    protected function checkLiveReminder(
        InspectionReminderService $reminderService,
        InspectionRandomService $randomService,
    ): void {
        if (! $reminderService->needsRandomReminder()) {
            return;
        }

        $level = $reminderService->getReminderLevel();
        $key = $randomService->currentHourSlot()->toDateTimeString().'-'.$level;

        if ($this->lastToastReminderKey === $key) {
            return;
        }

        $this->lastToastReminderKey = $key;

        $message = $reminderService->getReminderMessage($level);

        Notification::make()
            ->title($message['title'])
            ->body($message['body'])
            ->{$message['color']}()
            ->send();

        if ($level === 'urgent') {
            $this->dispatch('play-urgent-alert');
        }
    }

    public function resolveAssignment(): ?InspectionAssignment
    {
        if ($this->assignmentId === null) {
            return null;
        }

        return InspectionAssignment::query()
            ->with(['lane', 'inspection'])
            ->find($this->assignmentId);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('plate')
                    ->label('Placa')
                    ->required()
                    ->maxLength(20)
                    ->regex('/^[A-Za-z0-9-]{3,20}$/')
                    ->validationMessages([
                        'regex' => 'Use 3–20 caracteres: letras, números o guiones.',
                    ])
                    ->extraInputAttributes(['class' => 'uppercase'])
                    ->columnSpanFull(),
                Select::make('result')
                    ->label('Resultado')
                    ->options(collect(InspectionResult::cases())->mapWithKeys(
                        fn (InspectionResult $case) => [$case->value => $case->label()]
                    ))
                    ->required()
                    ->live()
                    ->native(false),
                Textarea::make('comments')
                    ->label('Comentarios')
                    ->rows(3)
                    ->required(fn (Get $get): bool => in_array($get('result'), [
                        InspectionResult::Rejected->value,
                        InspectionResult::Conditional->value,
                    ], true))
                    ->minLength(fn (Get $get): ?int => in_array($get('result'), [
                        InspectionResult::Rejected->value,
                        InspectionResult::Conditional->value,
                    ], true) ? 5 : null)
                    ->helperText('Obligatorio si el resultado es Rechazado o Condicional.')
                    ->columnSpanFull(),
            ])
            ->statePath('inspectionData');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRandom')
                ->label('Generar random')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->keyBindings(['mod+g'])
                ->visible(fn () => $this->resolveAssignment() === null)
                ->disabled(fn () => $this->eligibleLaneCount() === 0)
                ->tooltip(fn () => $this->eligibleLaneCount() === 0
                    ? 'No hay carriles abiertos. Monitoreo debe habilitarlos en Carriles según aviso del puerto.'
                    : null)
                ->requiresConfirmation()
                ->modalHeading('Generar random de inspección')
                ->modalDescription(fn () => $this->eligibleLaneCount() === 0
                    ? 'No hay carriles de entrada abiertos. Actualice el estado en Carriles antes de continuar.'
                    : sprintf(
                        'Se sorteará uno de los %d carriles abiertos para esta franja horaria. ¿Continuar?',
                        $this->eligibleLaneCount(),
                    ))
                ->action(function (InspectionRandomService $service): void {
                    $this->runInspectionAction(function () use ($service): void {
                        $assignment = $service->generateRandom($this->authenticatedUser());
                        $this->assignmentId = $assignment->id;
                        $this->lastToastReminderKey = null;
                        $this->bumpUiRevision();
                        $this->refreshDashboard();

                        Notification::make()
                            ->title('Random generado')
                            ->body("Carril asignado: {$assignment->lane->name}")
                            ->success()
                            ->send();
                    });
                }),
            Action::make('regenerate')
                ->label('Regenerar carril')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->keyBindings(['mod+shift+r'])
                ->visible(fn () => ($assignment = $this->resolveAssignment()) !== null && $assignment->inspection === null)
                ->form([
                    Textarea::make('regeneration_reason')
                        ->label('Motivo de regeneración')
                        ->helperText('Obligatorio. Mínimo 10 caracteres.')
                        ->required()
                        ->minLength(10)
                        ->rows(3),
                ])
                ->action(function (array $data, InspectionRandomService $service): void {
                    $this->runInspectionAction(function () use ($data, $service): void {
                        $assignment = $service->regenerate(
                            $this->authenticatedUser(),
                            $data['regeneration_reason'],
                            $this->resolveAssignment(),
                        );

                        $this->assignmentId = $assignment->id;
                        $this->bumpUiRevision();
                        $this->refreshDashboard();

                        Notification::make()
                            ->title('Random regenerado')
                            ->body("Nuevo carril: {$assignment->lane->name}")
                            ->success()
                            ->send();
                    });
                }),
        ];
    }

    public function registerInspection(InspectionRandomService $service): void
    {
        if ($this->resolveAssignment() === null) {
            Notification::make()
                ->title('No hay asignación activa')
                ->warning()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $assignment = $this->resolveAssignment();

        $this->runInspectionAction(function () use ($service, $data, $assignment): void {
            $service->registerInspection(
                $this->authenticatedUser(),
                $assignment,
                $data['plate'],
                InspectionResult::from($data['result']),
                $data['comments'] ?? null,
            );

            $this->refreshAssignment($service);
            $this->refreshDashboard();

            Notification::make()
                ->title('Inspección registrada')
                ->success()
                ->send();

            $this->resetInspectionForm();
        });
    }

    protected function runInspectionAction(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Operación no permitida')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Error de validación.')
                ->danger()
                ->send();
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        $hourSlot = app(InspectionRandomService::class)->currentHourSlot();

        return sprintf(
            'Franja horaria: %s – %s · %s',
            $hourSlot->format('H:i'),
            $hourSlot->copy()->addHour()->format('H:i'),
            config('app.timezone'),
        );
    }

    protected function refreshAssignment(InspectionRandomService $service): void
    {
        $this->assignmentId = $service->getCurrentHourAssignment()?->id;
        $this->bumpUiRevision();
    }

    protected function bumpUiRevision(): void
    {
        $this->uiRevision++;
    }

    protected function refreshDashboard(): void
    {
        $this->refreshKpis();
        $this->dispatch('inspection-control-updated');
    }

    protected function resetInspectionForm(): void
    {
        $this->form->fill([
            'plate' => '',
            'result' => InspectionResult::Approved->value,
            'comments' => '',
        ]);
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
