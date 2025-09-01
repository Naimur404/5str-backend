@extends('filament-panels::layout.base')

@section('content')
    <div class="filament-app-layout flex h-full w-full overflow-hidden">
        {{-- Sidebar --}}
        <x-filament-panels::sidebar />

        {{-- Main Content --}}
        <div class="flex flex-1 flex-col overflow-hidden">
            {{-- Topbar with Custom Logo --}}
            <x-filament-panels::topbar>
                <x-slot name="start">
                    <div class="flex items-center space-x-4">
                        {{-- Your Custom Logo --}}
                        <img src="{{ asset('images/logo.png') }}" 
                             alt="5SRT Business Discovery" 
                             class="h-8 w-auto">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            5SRT Business Discovery
                        </span>
                    </div>
                </x-slot>
            </x-filament-panels::topbar>

            {{-- Page Content --}}
            <main class="flex-1 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Custom styles for your logo */
    .fi-logo {
        height: 2.5rem !important;
    }
    
    /* Ensure logo displays well in dark mode */
    @media (prefers-color-scheme: dark) {
        .fi-logo {
            filter: brightness(1.1);
        }
    }
    
    /* Login page logo styling */
    .login-logo {
        max-height: 4rem;
        width: auto;
    }
</style>
@endpush
