@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="fi-auth-layout relative min-h-screen lg:grid lg:grid-cols-2">
        {{-- Panel de marca (escritorio) --}}
        <aside
            class="relative hidden overflow-hidden bg-gradient-to-br from-gray-100 via-gray-200 to-orange-100 lg:flex lg:flex-col lg:justify-between"
            aria-hidden="true"
        >
            <div
                class="pointer-events-none absolute inset-0 opacity-40"
                style="background-image: linear-gradient(rgba(0,0,0,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,.04) 1px, transparent 1px); background-size: 48px 48px;"
            ></div>

            <div class="relative z-10 flex flex-1 flex-col justify-center px-12 xl:px-16">
                <div class="mb-10 flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-500 ring-1 ring-orange-600/20">
                    <x-filament::icon
                        icon="heroicon-o-shield-check"
                        class="h-8 w-8 text-black"
                    />
                </div>

                <h1 class="max-w-lg text-4xl font-bold leading-tight tracking-tight text-black xl:text-5xl">
                    {{ config('app.name') }}
                </h1>

                <p class="mt-4 max-w-md text-lg leading-relaxed text-black">
                    Sorteo horario, registro de inspecciones y cumplimiento operativo en la terminal.
                </p>

                <ul class="mt-10 space-y-4 text-sm text-black">
                    <li class="flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-clock" class="mt-0.5 h-5 w-5 shrink-0 text-orange-600" />
                        <span>Random de inspección cada hora entre carriles de entrada</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-radio" class="mt-0.5 h-5 w-5 shrink-0 text-orange-600" />
                        <span>Aviso por radio al oficial y registro en sistema</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-chart-bar" class="mt-0.5 h-5 w-5 shrink-0 text-orange-600" />
                        <span>Cumplimiento del día visible en tiempo real</span>
                    </li>
                </ul>
            </div>

            <div class="relative z-10 border-t border-gray-300 px-12 py-6 xl:px-16">
                <p class="text-xs uppercase tracking-widest text-black">
                    Uso exclusivo en PC del puesto de monitoreo
                </p>
            </div>
        </aside>

        {{-- Panel del formulario --}}
        <main class="flex min-h-screen flex-col justify-center bg-gray-100 px-6 py-12 sm:px-10 lg:px-16">
            {{-- Marca en móvil / ventanas estrechas --}}
            <div class="mb-8 lg:hidden">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-500 text-black shadow-lg shadow-orange-500/20">
                        <x-filament::icon icon="heroicon-o-shield-check" class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-black">{{ config('app.name') }}</p>
                        <p class="text-xs text-black">Control de inspecciones</p>
                    </div>
                </div>
            </div>

            <div class="mx-auto w-full max-w-md">
                <div class="rounded-2xl bg-white p-8 shadow-xl shadow-gray-400/10 ring-1 ring-gray-300 sm:p-10">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-black">
                    {{ config('app.timezone') }} · {{ now()->format('d/m/Y') }}
                </p>
            </div>
        </main>
    </div>

    {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $livewire?->getRenderHookScopes()) }}
</x-filament-panels::layout.base>
