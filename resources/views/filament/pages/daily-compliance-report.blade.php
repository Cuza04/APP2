<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $breakdown = $this->breakdown;
        $regenerations = $this->regenerations;
        $weekly = $this->weeklySummary;
    @endphp

    <div class="space-y-6">
        <x-filament::section icon="heroicon-o-calendar-days" heading="Resumen semanal">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-black">
                    Semana del <strong>{{ $weekly['week_start'] }}</strong> al <strong>{{ $weekly['week_end'] }}</strong>
                </p>

                <div class="flex flex-wrap gap-3">
                    <x-filament::badge color="info" size="lg">
                        {{ $weekly['compliance_rate'] }}% cumplimiento
                    </x-filament::badge>
                    <x-filament::badge color="success" size="lg">
                        {{ $weekly['completed'] }} completadas
                    </x-filament::badge>
                    <x-filament::badge :color="$weekly['missed'] > 0 ? 'danger' : 'gray'" size="lg">
                        {{ $weekly['missed'] }} sin random
                    </x-filament::badge>
                    <x-filament::badge :color="$weekly['cancelled'] > 0 ? 'warning' : 'gray'" size="lg">
                        {{ $weekly['cancelled'] }} canceladas
                    </x-filament::badge>
                </div>
            </div>

            @if ($weekly['days']->isNotEmpty())
                <div class="mt-4 grid gap-2 md:grid-cols-7">
                    @foreach ($weekly['days'] as $day)
                        @php
                            $isSelected = $day['date'] === $this->date;
                            $dayColor = match (true) {
                                $day['compliance_rate'] >= 95 => 'success',
                                $day['compliance_rate'] >= 80 => 'warning',
                                default => 'danger',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:click="$set('date', '{{ $day['date'] }}')"
                            @class([
                                'rounded-lg border px-3 py-2 text-left text-sm transition',
                                'border-orange-500 bg-orange-50' => $isSelected,
                                'border-gray-300 hover:border-gray-400' => ! $isSelected,
                            ])
                        >
                            <p class="font-medium text-black">{{ $day['label'] }}</p>
                            <p class="mt-1 text-xs text-black">{{ $day['completed'] }}/{{ $day['total_due'] }} h</p>
                            <x-filament::badge :color="$dayColor" class="mt-2">
                                {{ $day['compliance_rate'] }}%
                            </x-filament::badge>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-xs">
                <label class="mb-1 block text-sm font-medium text-black">
                    Fecha
                </label>
                <input
                    type="date"
                    wire:model.live="date"
                    class="block w-full rounded-lg border-gray-300 text-black shadow-sm focus:border-orange-500 focus:ring-orange-500"
                />
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-filament::badge color="success" size="lg">
                    {{ $summary['completed'] }} completadas
                </x-filament::badge>
                <x-filament::badge color="danger" size="lg">
                    {{ $summary['missed'] }} sin random
                </x-filament::badge>
                <x-filament::badge color="warning" size="lg">
                    {{ $summary['cancelled'] }} canceladas
                </x-filament::badge>
                <x-filament::badge color="info" size="lg">
                    {{ $summary['compliance_rate'] }}% cumplimiento
                </x-filament::badge>
            </div>
        </div>

        <x-filament::section icon="heroicon-o-clock" heading="Franjas del día">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-300">
                            <th class="px-3 py-2 font-medium text-black">Franja</th>
                            <th class="px-3 py-2 font-medium text-black">Estado</th>
                            <th class="px-3 py-2 font-medium text-black">Carril</th>
                            <th class="px-3 py-2 font-medium text-black">Placa</th>
                            <th class="px-3 py-2 font-medium text-black">Resultado</th>
                            <th class="px-3 py-2 font-medium text-black">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($breakdown as $row)
                            @php
                                $badgeColor = match ($row['status']) {
                                    'completed' => 'success',
                                    'missed' => 'danger',
                                    'cancelled' => 'warning',
                                    'in_progress' => 'info',
                                    default => 'gray',
                                };
                            @endphp
                            <tr class="border-b border-gray-200">
                                <td class="px-3 py-2 font-mono text-black">{{ $row['hour_label'] }}</td>
                                <td class="px-3 py-2">
                                    <x-filament::badge :color="$badgeColor">
                                        {{ $row['status_label'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2 text-black">{{ $row['lane'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-black">{{ $row['plate'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-black">{{ $row['result'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-black">{{ $row['detail'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-black">
                                    No hay franjas registradas para esta fecha.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @if ($regenerations->isNotEmpty())
            <x-filament::section icon="heroicon-o-arrow-path" heading="Regeneraciones del día">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-300">
                                <th class="px-3 py-2 font-medium text-black">Hora</th>
                                <th class="px-3 py-2 font-medium text-black">Franja</th>
                                <th class="px-3 py-2 font-medium text-black">Cambio</th>
                                <th class="px-3 py-2 font-medium text-black">Motivo</th>
                                <th class="px-3 py-2 font-medium text-black">Operador</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($regenerations as $item)
                                <tr class="border-b border-gray-200">
                                    <td class="px-3 py-2 text-black">{{ $item['at']->format('H:i') }}</td>
                                    <td class="px-3 py-2 font-mono text-black">{{ $item['hour_slot'] }}</td>
                                    <td class="px-3 py-2 text-black">{{ $item['previous_lane'] }} → {{ $item['new_lane'] }}</td>
                                    <td class="px-3 py-2 text-black">{{ $item['reason'] }}</td>
                                    <td class="px-3 py-2 text-black">{{ $item['user'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
