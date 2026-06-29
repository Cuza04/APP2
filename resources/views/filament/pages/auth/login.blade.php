<div class="space-y-8">
    <header class="space-y-2">
        <h2 class="text-2xl font-bold tracking-tight text-black">
            {{ $this->getHeading() }}
        </h2>

        @if ($subheading = $this->getSubheading())
            <p class="text-sm leading-relaxed text-black">
                {{ $subheading }}
            </p>
        @endif
    </header>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form id="form" wire:submit="authenticate" class="space-y-6">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</div>
