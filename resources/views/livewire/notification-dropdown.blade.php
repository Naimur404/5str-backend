@php
    $notifications = $notifications ?? collect();
    $unreadCount = $unreadCount ?? 0;
@endphp

<div class="relative notification-dropdown" x-data="{ open: false }" @click.away="open = false">
    <!-- Notification Bell Icon -->
    <button 
        x-ref="button"
        @click="open = !open"
        class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 rounded-md transition-colors duration-200"
        type="button"
        title="Notifications"
    >
        <x-heroicon-o-bell class="w-6 h-6" />
        
        <!-- Unread Badge -->
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full min-w-[20px] h-5">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown Panel -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed top-16 right-4 z-[9999] w-80 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-800 rounded-lg shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none"
        style="display: none; max-height: calc(100vh - 5rem);"
        x-cloak
    >
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-t-lg">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Notifications
                    @if($unreadCount > 0)
                        <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </h3>
                @if($unreadCount > 0)
                    <button 
                        wire:click="markAllAsRead"
                        class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium transition-colors duration-200"
                    >
                        Mark all read
                    </button>
                @endif
            </div>
        </div>

        <!-- Notification List -->
        <div class="max-h-64 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600">
            @forelse($notifications as $notification)
                <div 
                    class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors duration-200 {{ !$notification->is_read ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-l-blue-500' : '' }}"
                    wire:click="markAsRead({{ $notification->id }})"
                >
                    <div class="flex items-start space-x-3">
                        <!-- Icon based on notification type -->
                        <div class="flex-shrink-0">
                            @switch($notification->type)
                                @case('business_approved')
                                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                                    @break
                                @case('business_rejected')
                                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-500" />
                                    @break
                                @case('review_received')
                                    <x-heroicon-o-star class="w-5 h-5 text-yellow-500" />
                                    @break
                                @case('offer_created')
                                    <x-heroicon-o-gift class="w-5 h-5 text-purple-500" />
                                    @break
                                @case('system_announcement')
                                    <x-heroicon-o-megaphone class="w-5 h-5 text-blue-500" />
                                    @break
                                @case('alert')
                                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                                    @break
                                @default
                                    <x-heroicon-o-bell class="w-5 h-5 text-gray-500" />
                            @endswitch
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $notification->title }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $notification->message }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                        
                        <!-- Unread indicator -->
                        @if(!$notification->is_read)
                            <div class="flex-shrink-0">
                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <x-heroicon-o-bell-slash class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        No notifications yet
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        @if($notifications->count() > 0)
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 rounded-b-lg border-t border-gray-200 dark:border-gray-600">
                <a 
                    href="{{ route('filament.admin.resources.notifications.index') }}"
                    class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium transition-colors duration-200"
                    @click="open = false"
                >
                    View all notifications
                </a>
            </div>
        @endif
    </div>
</div>
