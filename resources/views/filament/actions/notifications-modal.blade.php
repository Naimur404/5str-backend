<div class="space-y-4">
    @if($unreadCount > 0)
        <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                You have {{ $unreadCount }} unread notification{{ $unreadCount > 1 ? 's' : '' }}
            </span>
        </div>
    @endif

    <div class="space-y-2 max-h-96 overflow-y-auto">
        @forelse($notifications as $notification)
            <div 
                class="p-4 rounded-lg border {{ !$notification->is_read ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-600' }} cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                wire:click="markAsRead({{ $notification->id }})"
            >
                <div class="flex items-start space-x-3">
                    <!-- Icon -->
                    <div class="flex-shrink-0 mt-1">
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
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $notification->title }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $notification->message }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
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
            <div class="text-center py-8">
                <x-heroicon-o-bell-slash class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    No notifications yet
                </p>
            </div>
        @endforelse
    </div>
</div>
