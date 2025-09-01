<x-filament-panels::page.simple>
    @if (filament()->hasLogin())
        <x-slot name="heading">
            <div class="flex flex-col items-center">
                {{-- Custom Logo Display (this replaces the default brand logo) --}}
                <div class="mb-6">
                    <img src="{{ asset('images/logo.png') }}" 
                         alt="5SRT Business Discovery" 
                         class="h-16 w-auto mx-auto login-custom-logo">
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

    {{-- Custom CSS to hide the default brand logo --}}
    @push('styles')
    <style>
        /* Hide the default Filament brand logo on login page */
        .fi-simple-page .fi-logo,
        .fi-simple-layout .fi-logo,
        .fi-brand,
        .fi-sidebar-header,
        .fi-topbar-brand,
        .fi-brand-logo {
            display: none !important;
        }
        
        /* Hide any brand-related elements */
        [class*="brand"],
        [class*="logo"]:not(.login-custom-logo) {
            display: none !important;
        }
        
        /* Ensure our custom logo is prominent */
        .login-custom-logo {
            max-height: 4rem;
            width: auto;
        }
        
        /* Simple white background */
        .fi-simple-page {
            background: white;
        }
        
        .fi-simple-main {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
    @endpush
</x-filament-panels::page.simple>
