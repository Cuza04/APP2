<x-filament-panels::page wire:poll.30s="pollReminders">
    @php
        $assignment = $this->resolveAssignment();
        $hourSlot = app(\App\Services\InspectionRandomService::class)->currentHourSlot();
        $reminderLevel = $this->resolveReminderLevel();
        $minutesIntoHour = app(\App\Services\InspectionReminderService::class)->minutesIntoCurrentHour();
        $timezone = config('app.timezone');
        $uiRevision = $this->uiRevision;
        $kpiRevision = $this->kpiRevision;
        $dailySummary = $this->dailySummary();
        $complianceRateTone = $this->complianceRateTone($dailySummary['compliance_rate']);
        $missedTone = $this->missedTone($dailySummary['missed']);
    @endphp

    <div
        wire:key="inspection-panel-{{ $uiRevision }}-{{ $assignment?->id ?? 'none' }}-{{ $assignment?->inspection?->id ?? 'pending' }}"
        class="space-y-6"
        x-data="{
            soundEnabled: localStorage.getItem('inspection-sound-alerts') === '1',
            toggleSound() {
                this.soundEnabled = ! this.soundEnabled;
                localStorage.setItem('inspection-sound-alerts', this.soundEnabled ? '1' : '0');
            },
            playUrgentAlert() {
                if (! this.soundEnabled) {
                    return;
                }

                try {
                    const context = new (window.AudioContext || window.webkitAudioContext)();
                    [0, 500].forEach((delay) => {
                        setTimeout(() => {
                            const oscillator = context.createOscillator();
                            const gain = context.createGain();
                            oscillator.type = 'square';
                            oscillator.frequency.value = 880;
                            gain.gain.value = 0.12;
                            oscillator.connect(gain);
                            gain.connect(context.destination);
                            oscillator.start();
                            setTimeout(() => oscillator.stop(), 300);
                        }, delay);
                    });
                } catch (error) {
                    console.warn('No se pudo reproducir la alerta sonora.', error);
                }
            },
        }"
        x-on:play-urgent-alert.window="playUrgentAlert()"
    >
        <div
            wire:key="kpis-{{ $kpiRevision }}"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div class="rounded-xl border {{ $complianceRateTone }} px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-black">Cumplimiento hoy</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-black">{{ $dailySummary['compliance_rate'] }}%</p>
                <p class="text-xs text-black">{{ $dailySummary['completed'] }} / {{ $dailySummary['total_due'] }} horas</p>
            </div>
            <div class="rounded-xl border border-emerald-400 bg-emerald-50 px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-black">Completadas</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-black">{{ $dailySummary['completed'] }}</p>
            </div>
            <div class="rounded-xl border {{ $missedTone }} px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-black">Sin random</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-black">{{ $dailySummary['missed'] }}</p>
            </div>
            <div class="rounded-xl border {{ $complianceRateTone }} px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-black">Estado del día</p>
                <p class="mt-1 text-lg font-bold text-black">
                    {{ $this->dailyStatusLabel($dailySummary['compliance_rate']) }}
                </p>
                <p class="text-xs text-black">{{ $dailySummary['date'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-sky-300 bg-sky-50 px-5 py-4 shadow-sm">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-black">
                    Reloj del terminal · {{ $timezone }}
                </p>
                <p
                    class="font-mono text-3xl font-bold tabular-nums text-black"
                    x-data="{
                        time: '',
                        init() {
                            this.update();
                            setInterval(() => this.update(), 1000);
                        },
                        update() {
                            this.time = new Intl.DateTimeFormat('es', {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit',
                                hour12: false,
                                timeZone: @js($timezone),
                            }).format(new Date());
                        },
                    }"
                    x-text="time"
                ></p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm text-black">
                    Franja actual:
                    <strong>{{ $hourSlot->format('H:i') }} – {{ $hourSlot->copy()->addHour()->format('H:i') }}</strong>
                </p>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1 transition"
                    x-bind:class="soundEnabled
                        ? 'bg-emerald-50 text-emerald-950 ring-emerald-300'
                        : 'bg-white text-black ring-gray-300 hover:bg-gray-50'"
                    x-on:click="toggleSound()"
                >
                    <x-filament::icon
                        icon="heroicon-o-speaker-wave"
                        class="h-4 w-4"
                        x-show="soundEnabled"
                        x-cloak
                    />
                    <x-filament::icon
                        icon="heroicon-o-speaker-x-mark"
                        class="h-4 w-4"
                        x-show="! soundEnabled"
                    />
                    <span x-text="soundEnabled ? 'Alertas sonoras activas' : 'Activar alertas sonoras'"></span>
                </button>
            </div>
        </div>

        @if ($assignment === null)
            @php
                $bannerColor = match ($reminderLevel) {
                    'urgent' => 'danger',
                    'warning' => 'warning',
                    default => 'info',
                };

                $bannerMessage = $this->reminderBannerMessage();
            @endphp

            <x-filament::section
                :icon="$reminderLevel === 'urgent' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-bell-alert'"
                :icon-color="$bannerColor"
                @class([
                    'ring-2 ring-danger-500 animate-pulse' => $reminderLevel === 'urgent',
                ])
            >
                <x-slot name="heading">
                    Sin random para esta hora
                </x-slot>

                <div class="flex items-center justify-between gap-4">
                    <div class="space-y-2">
                        <p class="text-sm text-black">
                            Aún no se ha generado el random para la franja
                            <strong>{{ $hourSlot->format('H:i') }} – {{ $hourSlot->copy()->addHour()->format('H:i') }}</strong>.
                            Pulsa <strong>Generar random</strong> y avisa por radio al oficial del carril que salga.
                        </p>

                        @if ($reminderLevel !== null && $bannerMessage !== null)
                            <p class="text-sm font-medium text-black">
                                {{ $bannerMessage }}
                                <span class="text-black">({{ $minutesIntoHour }} min transcurridos)</span>
                            </p>
                        @endif
                    </div>

                    <x-filament::badge :color="$bannerColor" icon="heroicon-o-bell-alert">
                        @if ($reminderLevel === 'urgent')
                            Urgente
                        @elseif ($reminderLevel === 'warning')
                            Recordatorio
                        @else
                            Pendiente de generar
                        @endif
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @else
            <div @class([
                'grid gap-6',
                'lg:grid-cols-1' => $assignment->inspection === null,
            ])>
                @if ($assignment->inspection === null)
                    <div class="rounded-2xl border-4 border-orange-500 bg-orange-50 p-8 shadow-lg ring-4 ring-orange-200">
                        <div class="flex flex-col items-center gap-6 text-center sm:flex-row sm:text-left">
                            <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-orange-500 text-black shadow-md">
                                <x-filament::icon
                                    icon="heroicon-o-megaphone"
                                    class="h-12 w-12"
                                />
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-black">
                                    Inspección requerida · avisar por radio
                                </p>
                                <p class="text-5xl font-black leading-none text-black sm:text-6xl">
                                    {{ $assignment->lane->name }}
                                </p>
                                <p class="text-xl font-bold text-black">
                                    Código {{ $assignment->lane->code }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <x-filament::section icon="heroicon-o-clipboard-document-list">
                        <x-slot name="heading">
                            Registrar inspección
                        </x-slot>

                        <x-slot name="description">
                            Completa los datos después de que el oficial inspeccione el camión en {{ $assignment->lane->name }}.
                        </x-slot>

                        <form
                            wire:submit="registerInspection"
                            class="space-y-4"
                            x-data
                            x-mousetrap.global.mod+s.prevent="$wire.registerInspection()"
                        >
                            {{ $this->form }}

                            <div class="flex items-center justify-between gap-4">
                                <x-filament::button type="submit" icon="heroicon-o-check">
                                    Guardar inspección
                                </x-filament::button>
                                <p class="text-xs text-black">
                                    Atajo: <kbd class="rounded border px-1.5 py-0.5 font-mono text-[10px]">Ctrl</kbd>+<kbd class="rounded border px-1.5 py-0.5 font-mono text-[10px]">S</kbd>
                                </p>
                            </div>
                        </form>
                    </x-filament::section>
                @else
                    <x-filament::section icon="heroicon-o-check-circle" icon-color="primary" class="lg:col-span-2">
                        <x-slot name="heading">
                            Inspección completada esta hora
                        </x-slot>

                        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="font-medium text-black">Carril</dt>
                                <dd>{{ $assignment->lane->name }} ({{ $assignment->lane->code }})</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-black">Placa</dt>
                                <dd>{{ $assignment->inspection->plate }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-black">Resultado</dt>
                                <dd>{{ $assignment->inspection->result->label() }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-black">Registrado</dt>
                                <dd>{{ $assignment->inspection->inspected_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            @if (filled($assignment->inspection->comments))
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <dt class="font-medium text-black">Comentarios</dt>
                                    <dd class="mt-1 whitespace-pre-wrap">{{ $assignment->inspection->comments }}</dd>
                                </div>
                            @endif
                        </dl>
                    </x-filament::section>
                @endif
            </div>
        @endif

        <p class="text-xs text-black">
            Atajos de teclado:
            @if ($assignment === null)
                <kbd class="rounded border px-1.5 py-0.5 font-mono">Ctrl</kbd>+<kbd class="rounded border px-1.5 py-0.5 font-mono">G</kbd> generar random
            @elseif ($assignment->inspection === null)
                <kbd class="rounded border px-1.5 py-0.5 font-mono">Ctrl</kbd>+<kbd class="rounded border px-1.5 py-0.5 font-mono">G</kbd> no disponible ·
                <kbd class="rounded border px-1.5 py-0.5 font-mono">Ctrl</kbd>+<kbd class="rounded border px-1.5 py-0.5 font-mono">Shift</kbd>+<kbd class="rounded border px-1.5 py-0.5 font-mono">R</kbd> regenerar carril ·
                <kbd class="rounded border px-1.5 py-0.5 font-mono">Ctrl</kbd>+<kbd class="rounded border px-1.5 py-0.5 font-mono">S</kbd> guardar inspección
            @endif
        </p>
    </div>
</x-filament-panels::page>
