<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header with description -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">API Analytics Overview</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Monitor your API endpoints performance, user engagement, and geographic usage patterns. 
                Track real-time analytics to make data-driven decisions for your application.
            </p>
        </div>
        
        <!-- Key Metrics Section -->
        <div>
            <h3 class="text-base font-medium text-gray-900 dark:text-white mb-4">Key Performance Metrics</h3>
            <x-filament-widgets::widgets 
                :widgets="$this->getHeaderWidgets()" 
                :columns="$this->getHeaderWidgetsColumns()"
            />
        </div>
        
        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <h3 class="text-base font-medium text-gray-900 dark:text-white mb-4">Usage Trends</h3>
                <x-filament-widgets::widgets 
                    :widgets="[$this->getFooterWidgets()[0]]" 
                    :columns="1"
                />
            </div>
            <div>
                <h3 class="text-base font-medium text-gray-900 dark:text-white mb-4">Geographic Distribution</h3>
                <x-filament-widgets::widgets 
                    :widgets="[$this->getFooterWidgets()[1]]" 
                    :columns="1"
                />
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h3 class="text-base font-medium text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ route('filament.admin.resources.endpoint-analytics.index') }}" 
                   class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                    <x-heroicon-o-table-cells class="w-6 h-6 text-blue-600 dark:text-blue-400 mr-3" />
                    <div>
                        <p class="font-medium text-blue-900 dark:text-blue-100">View All Analytics</p>
                        <p class="text-sm text-blue-600 dark:text-blue-300">Browse detailed analytics data</p>
                    </div>
                </a>
                
                <a href="{{ route('filament.admin.resources.search-logs.index') }}" 
                   class="flex items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                    <x-heroicon-o-magnifying-glass class="w-6 h-6 text-green-600 dark:text-green-400 mr-3" />
                    <div>
                        <p class="font-medium text-green-900 dark:text-green-100">Search Logs</p>
                        <p class="text-sm text-green-600 dark:text-green-300">Analyze search patterns</p>
                    </div>
                </a>
                
                <a href="{{ route('filament.admin.resources.views.index') }}" 
                   class="flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                    <x-heroicon-o-eye class="w-6 h-6 text-purple-600 dark:text-purple-400 mr-3" />
                    <div>
                        <p class="font-medium text-purple-900 dark:text-purple-100">Page Views</p>
                        <p class="text-sm text-purple-600 dark:text-purple-300">Track page interactions</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Additional Insights -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6">
            <h3 class="text-base font-medium text-gray-900 dark:text-white mb-2">Analytics Insights</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="font-medium text-gray-900 dark:text-white mb-1">Real-time Tracking</p>
                    <p class="text-gray-600 dark:text-gray-400">All API endpoints are automatically tracked with location data and user analytics.</p>
                </div>
                <div>
                    <p class="font-medium text-gray-900 dark:text-white mb-1">Data Retention</p>
                    <p class="text-gray-600 dark:text-gray-400">Analytics data is retained for comprehensive historical analysis and trend identification.</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
