<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Platform Statistics -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Platform Statistics</h3>
                <p class="text-sm text-gray-600">Key metrics for your business discovery platform</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Total Users -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-blue-600">{{ $totalUsers }}</h4>
                        <p class="text-sm text-gray-600">Registered Users</p>
                    </div>

                    <!-- Total Businesses -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-green-600">{{ $totalBusinesses }}</h4>
                        <p class="text-sm text-gray-600">Active Businesses</p>
                    </div>

                    <!-- Pending Approvals -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-yellow-600">{{ $pendingApprovals }}</h4>
                        <p class="text-sm text-gray-600">Pending Approvals</p>
                    </div>

                    <!-- Active Today -->
                    <div class="text-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h4 class="text-2xl font-bold text-purple-600">{{ $activeToday }}</h4>
                        <p class="text-sm text-gray-600">Active Today</p>
                    </div>
                </div>
            </div>
        </div>

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

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                <p class="text-sm text-gray-600">Manage your platform efficiently</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="#" class="block p-4 border-2 border-dashed border-gray-300 rounded-lg text-center hover:border-gray-400 transition-colors">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Manage Businesses</span>
                    </a>
                    
                    <a href="#" class="block p-4 border-2 border-dashed border-gray-300 rounded-lg text-center hover:border-gray-400 transition-colors">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Manage Users</span>
                    </a>
                    
                    <a href="#" class="block p-4 border-2 border-dashed border-gray-300 rounded-lg text-center hover:border-gray-400 transition-colors">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2 2z"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-900">View Analytics</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
