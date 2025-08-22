<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Platform Statistics -->
        

        

        <!-- Analytics Section - Automatic Tracking -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Analytics (Automatic Tracking)</h3>
                <p class="text-sm text-gray-600">Real-time analytics automatically tracked by the system</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Searches Today -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-indigo-600">{{ $searchesToday }}</h4>
                        <p class="text-sm text-gray-600">Searches Today</p>
                        <p class="text-xs text-green-600 mt-1">✓ Auto Tracked</p>
                    </div>

                    <!-- Views Today -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-pink-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-pink-600">{{ $viewsToday }}</h4>
                        <p class="text-sm text-gray-600">Business Views Today</p>
                        <p class="text-xs text-green-600 mt-1">✓ Auto Tracked</p>
                    </div>

                    <!-- Trending Data -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-orange-600">{{ $trendingBusinesses }}</h4>
                        <p class="text-sm text-gray-600">Trending Businesses</p>
                        <p class="text-xs text-green-600 mt-1">✓ Auto Calculated</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Default Dashboard Widgets -->
        <x-filament-widgets::widgets
            :widgets="$this->getWidgets()"
            :columns="$this->getColumns()"
        />
    </div>
</x-filament-panels::page>
