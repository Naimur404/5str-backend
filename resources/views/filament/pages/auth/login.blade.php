<x-filament-panels::page.simple>
    @if (filament()->hasLogin())
        <x-slot name="heading">
            <div class="flex flex-col items-center">
                {{-- Custom Logo Display --}}
                <div class="mb-6">
                    <img src="{{ asset('images/logo.png') }}" 
                         alt="5SRT Business Discovery" 
                         class="h-16 w-auto mx-auto">
                </div>
                
                {{-- Heading --}}
                <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $this->getHeading() }}
                </h1>
                
                {{-- Subheading --}}
                @if($this->getSubheading())
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->getSubheading() }}
                    </p>
                @endif
            </div>
        </x-slot>

        {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.before') }}

        <x-filament-panels::form wire:submit="authenticate">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>

        {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.after') }}
    @endif
</x-filament-panels::page.simple>
