<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Analytics Access
        </x-slot>
        
        <x-slot name="description">
            Access detailed analytics and monitoring tools for your platform
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" 
                   class="block p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors group bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: {{ match($action['color']) {
                                'blue' => 'rgb(239 246 255)',
                                'purple' => 'rgb(250 245 255)',
                                'green' => 'rgb(240 253 244)',
                                'orange' => 'rgb(255 247 237)',
                                default => 'rgb(243 244 246)'
                            } }}">
                                <svg class="w-5 h-5" style="color: {{ match($action['color']) {
                                    'blue' => 'rgb(37 99 235)',
                                    'purple' => 'rgb(147 51 234)',
                                    'green' => 'rgb(34 197 94)',
                                    'orange' => 'rgb(249 115 22)',
                                    default => 'rgb(107 114 128)'
                                } }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($action['icon'] === 'heroicon-o-chart-pie')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                                    @elseif($action['icon'] === 'heroicon-o-table-cells')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4M8 7h8M8 7H3m5 4h8m-8 4h8"></path>
                                    @elseif($action['icon'] === 'heroicon-o-magnifying-glass')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    @elseif($action['icon'] === 'heroicon-o-eye')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    @endif
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 group-hover:text-gray-700 dark:group-hover:text-gray-200">
                                {{ $action['title'] }}
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ $action['description'] }}
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
