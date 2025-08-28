<?php

namespace App\Services;

use App\Models\SearchLog;
use App\Models\View;
use App\Models\TrendingData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\LocationService;

class AnalyticsService
{
    protected $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Log a search query
     */
    public function logSearch(
        ?string $searchTerm = null,
        ?int $categoryId = null,
        ?float $userLatitude = null,
        ?float $userLongitude = null,
        ?string $userArea = null,
        ?array $filtersApplied = null,
        int $resultsCount = 0,
        ?int $clickedBusinessId = null,
        Request $request = null
    ): SearchLog {
        return SearchLog::create([
            'user_id' => Auth::id(),
            'search_term' => $searchTerm,
            'category_id' => $categoryId,
            'user_latitude' => $userLatitude,
            'user_longitude' => $userLongitude,
            'user_area' => $userArea,
            'filters_applied' => $filtersApplied,
            'results_count' => $resultsCount,
            'clicked_business_id' => $clickedBusinessId,
        ]);
    }

    /**
     * Log business view for trending analysis
     */
    public function logBusinessView(
        int $businessId,
        ?float $userLatitude = null,
        ?float $userLongitude = null,
        ?string $userArea = null,
        Request $request = null
    ): View {
        $business = \App\Models\Business::find($businessId);
        if (!$business) {
            throw new \Exception("Business not found with ID: {$businessId}");
        }

        $request = $request ?: request();
        
        // Determine user area from coordinates if not provided
        if (!$userArea && $userLatitude && $userLongitude) {
            $userArea = $this->locationService->determineUserAreaPrecise($userLatitude, $userLongitude);
        }
        
        $view = View::create([
            'user_id' => Auth::id(),
            'viewable_type' => 'App\Models\Business',
            'viewable_id' => $businessId,
            'user_latitude' => $userLatitude,
            'user_longitude' => $userLongitude,
            'user_area' => $userArea,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'Console Command',
            'session_id' => $request->hasSession() ? $request->session()->getId() : 'console-session',
        ]);

        // Update business view count for real-time trending
        $this->updateBusinessTrendingRealtime($businessId, $userArea);
        
        return $view;
    }

    /**
     * Log offering view for trending analysis
     */
    public function logOfferingView(
        int $offeringId,
        int $businessId,
        ?float $userLatitude = null,
        ?float $userLongitude = null,
        ?string $userArea = null,
        Request $request = null
    ): View {
        $offering = \App\Models\BusinessOffering::find($offeringId);
        if (!$offering) {
            throw new \Exception("Offering not found with ID: {$offeringId}");
        }

        $request = $request ?: request();
        
        // Determine user area from coordinates if not provided
        if (!$userArea && $userLatitude && $userLongitude) {
               $userArea = $this->locationService->determineUserAreaPrecise($userLatitude, $userLongitude);
        }
        
        $view = View::create([
            'user_id' => Auth::id(),
            'viewable_type' => 'App\Models\BusinessOffering',
            'viewable_id' => $offeringId,
            'user_latitude' => $userLatitude,
            'user_longitude' => $userLongitude,
            'user_area' => $userArea,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'Console Command',
            'session_id' => $request->hasSession() ? $request->session()->getId() : 'console-session',
        ]);

        // Update offering trending for real-time analysis
        $this->updateOfferingTrendingRealtime($offeringId, $userArea);
        
        return $view;
    }

    /**
     * Log a view for any model
     */
    public function logView($viewable, Request $request = null): View
    {
        $request = $request ?: request();
        
        return View::create([
            'user_id' => Auth::id(),
            'viewable_type' => get_class($viewable),
            'viewable_id' => $viewable->id,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'Console Command',
            'session_id' => $request->hasSession() ? $request->session()->getId() : 'console-session',
        ]);
    }

    /**
     * Update business click tracking in search log
     */
    public function updateSearchClick(int $searchLogId, int $businessId): bool
    {
        $searchLog = SearchLog::find($searchLogId);
        if ($searchLog) {
            $searchLog->update(['clicked_business_id' => $businessId]);
            return true;
        }
        return false;
    }

    /**
     * Calculate trending data for businesses with location analysis
     */
    public function calculateBusinessTrending(string $timePeriod = 'daily', ?string $date = null): void
    {
        $date = $date ?: now()->format('Y-m-d');
        $startDate = $this->getStartDate($timePeriod, $date);
        
        // Get business search counts by area
        $businessSearches = SearchLog::query()
            ->whereNotNull('clicked_business_id')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('clicked_business_id, user_area, COUNT(*) as search_count')
            ->groupBy(['clicked_business_id', 'user_area'])
            ->get();

        // Get business view counts by area
        $businessViews = View::query()
            ->where('viewable_type', 'App\\Models\\Business')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('viewable_id, user_area, COUNT(*) as view_count')
            ->groupBy(['viewable_id', 'user_area'])
            ->get();

        // Combine data and calculate trend scores by location
        $businessData = [];
        
        foreach ($businessSearches as $search) {
            $key = $search->clicked_business_id . '_' . ($search->user_area ?? 'unknown');
            $businessData[$key] = [
                'business_id' => $search->clicked_business_id,
                'area' => $search->user_area,
                'searches' => $search->search_count,
                'views' => 0
            ];
        }

        foreach ($businessViews as $view) {
            $key = $view->viewable_id . '_' . ($view->user_area ?? 'unknown');
            if (!isset($businessData[$key])) {
                $businessData[$key] = [
                    'business_id' => $view->viewable_id,
                    'area' => $view->user_area,
                    'searches' => 0, 
                    'views' => 0
                ];
            }
            $businessData[$key]['views'] = $view->view_count;
        }

        // Calculate and store trending data
        foreach ($businessData as $data) {
            $business = \App\Models\Business::find($data['business_id']);
            if ($business) {
                $trendScore = $this->calculateTrendScore($data['searches'], $data['views']);
                $hybridScore = $this->calculateHybridScore($trendScore, $business->overall_rating ?? 0);
                
                TrendingData::updateOrCreate([
                    'item_type' => 'business',
                    'item_id' => $data['business_id'],
                    'time_period' => $timePeriod,
                    'date_period' => $date,
                    'location_area' => $data['area'] ?? $business->area,
                ], [
                    'item_name' => $business->business_name,
                    'trend_score' => $trendScore,
                    'hybrid_score' => $hybridScore,
                    'search_count' => $data['searches'],
                    'view_count' => $data['views'],
                ]);
            }
        }
    }

    /**
     * Calculate trending data for categories
     */
    public function calculateCategoryTrending(string $timePeriod = 'daily', ?string $date = null): void
    {
        $date = $date ?: now()->format('Y-m-d');
        $startDate = $this->getStartDate($timePeriod, $date);
        
        // Get category search counts
        $categorySearches = SearchLog::query()
            ->whereNotNull('category_id')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('category_id, COUNT(*) as search_count')
            ->groupBy('category_id')
            ->get();

        foreach ($categorySearches as $search) {
            $category = \App\Models\Category::find($search->category_id);
            if ($category) {
                $trendScore = $this->calculateTrendScore($search->search_count, 0);
                
                TrendingData::updateOrCreate([
                    'item_type' => 'category',
                    'item_id' => $search->category_id,
                    'time_period' => $timePeriod,
                    'date_period' => $date,
                    'location_area' => null,
                ], [
                    'item_name' => $category->name,
                    'trend_score' => $trendScore,
                ]);
            }
        }
    }

    /**
     * Calculate trending data for offerings
     */
    public function calculateOfferingTrending(string $timePeriod = 'daily', ?string $date = null): void
    {
        $date = $date ?: now()->format('Y-m-d');
        $startDate = $this->getStartDate($timePeriod, $date);
        
        // Get offering view counts
        $offeringViews = View::query()
            ->where('viewable_type', 'App\\Models\\BusinessOffering')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('viewable_id, COUNT(*) as view_count')
            ->groupBy('viewable_id')
            ->get();

        // Calculate and store trending data for offerings
        foreach ($offeringViews as $view) {
            $offering = \App\Models\BusinessOffering::find($view->viewable_id);
            if ($offering && $offering->business) {
                $trendScore = $this->calculateTrendScore(0, $view->view_count); // Offerings don't have direct searches
                
                TrendingData::updateOrCreate([
                    'item_type' => 'offering',
                    'item_id' => $view->viewable_id,
                    'time_period' => $timePeriod,
                    'date_period' => $date,
                    'location_area' => $offering->business->area,
                ], [
                    'item_name' => $offering->name,
                    'trend_score' => $trendScore,
                ]);
            }
        }
    }

    /**
     * Calculate trending data for search terms
     */
    public function calculateSearchTermTrending(string $timePeriod = 'daily', ?string $date = null): void
    {
        $date = $date ?: now()->format('Y-m-d');
        $startDate = $this->getStartDate($timePeriod, $date);
        
        // Get search term counts
        $searchTerms = SearchLog::query()
            ->whereNotNull('search_term')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('search_term, COUNT(*) as search_count')
            ->groupBy('search_term')
            ->orderBy('search_count', 'desc')
            ->limit(100) // Top 100 search terms
            ->get();

        foreach ($searchTerms as $term) {
            $trendScore = $this->calculateTrendScore($term->search_count, 0);
            
            TrendingData::updateOrCreate([
                'item_type' => 'search_term',
                'item_id' => null,
                'item_name' => $term->search_term,
                'time_period' => $timePeriod,
                'date_period' => $date,
                'location_area' => null,
            ], [
                'trend_score' => $trendScore,
            ]);
        }
    }

    /**
     * Run all trending calculations
     */
    public function calculateAllTrending(string $timePeriod = 'daily', ?string $date = null): void
    {
        $this->calculateBusinessTrending($timePeriod, $date);
        $this->calculateCategoryTrending($timePeriod, $date);
        $this->calculateOfferingTrending($timePeriod, $date);
        $this->calculateSearchTermTrending($timePeriod, $date);
    }

    /**
     * Calculate trend score based on searches and views
     */
    private function calculateTrendScore(int $searches, int $views): float
    {
        // Weight: 70% searches, 30% views
        $searchScore = min(100, $searches * 5); // Cap at 100
        $viewScore = min(100, $views * 2); // Cap at 100
        
        return ($searchScore * 0.7) + ($viewScore * 0.3);
    }

    /**
     * Calculate hybrid score combining trending and rating
     */
    private function calculateHybridScore(float $trendScore, float $rating): float
    {
        // Normalize rating to 0-100 scale (assuming 5-star rating)
        $normalizedRating = ($rating / 5) * 100;
        
        // Weight: 60% trending, 40% rating
        return ($trendScore * 0.6) + ($normalizedRating * 0.4);
    }

    /**
     * Update business trending in real-time
     */
    private function updateBusinessTrendingRealtime(int $businessId, ?string $userArea): void
    {
        try {
            $today = now()->format('Y-m-d');
            $business = \App\Models\Business::find($businessId);
            
            if ($business && $userArea) { // Only update if userArea is provided
                // Get current trend data or create new
                $trendData = TrendingData::firstOrCreate([
                    'item_type' => 'business',
                    'item_id' => $businessId,
                    'time_period' => 'daily',
                    'date_period' => $today,
                    'location_area' => $userArea, // Use only actual user area
                ], [
                    'item_name' => $business->business_name,
                    'trend_score' => 0,
                    'hybrid_score' => 0,
                    'view_count' => 0,
                    'search_count' => 0,
                ]);

                // Increment view count and recalculate scores
                $trendData->increment('view_count');
                $newTrendScore = $this->calculateTrendScore($trendData->search_count, $trendData->view_count);
                $newHybridScore = $this->calculateHybridScore($newTrendScore, $business->overall_rating ?? 0);
                
                $trendData->update([
                    'trend_score' => $newTrendScore,
                    'hybrid_score' => $newHybridScore,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update business trending realtime: ' . $e->getMessage());
        }
    }

    /**
     * Update offering trending in real-time
     */
    private function updateOfferingTrendingRealtime(int $offeringId, ?string $userArea): void
    {
        try {
            $today = now()->format('Y-m-d');
            $offering = \App\Models\BusinessOffering::find($offeringId);
            
            if ($offering && $offering->business && $userArea) { // Only update if userArea is provided
                // Get current trend data or create new
                $trendData = TrendingData::firstOrCreate([
                    'item_type' => 'offering',
                    'item_id' => $offeringId,
                    'time_period' => 'daily',
                    'date_period' => $today,
                    'location_area' => $userArea, // Use only actual user area
                ], [
                    'item_name' => $offering->name,
                    'trend_score' => 0,
                    'hybrid_score' => 0,
                    'view_count' => 0,
                    'search_count' => 0,
                ]);

                // Increment view count and recalculate scores
                $trendData->increment('view_count');
                $newTrendScore = $this->calculateTrendScore($trendData->search_count, $trendData->view_count);
                $newHybridScore = $this->calculateHybridScore($newTrendScore, $offering->average_rating ?? 0);
                
                $trendData->update([
                    'trend_score' => $newTrendScore,
                    'hybrid_score' => $newHybridScore,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update offering trending realtime: ' . $e->getMessage());
        }
    }

    /**
     * Get trending businesses by location with hybrid scoring
     */
    public function getTrendingBusinesses(
        string $userArea = null,
        string $timePeriod = 'daily',
        int $limit = 20
    ): \Illuminate\Database\Eloquent\Collection {
        $today = now()->format('Y-m-d');
        
        $query = TrendingData::where('item_type', 'business')
            ->where('time_period', $timePeriod)
            ->where('date_period', $today)
            ->with('business.category', 'business.logoImage');

        if ($userArea) {
            $query->where('location_area', $userArea);
        }

        return $query->orderBy('hybrid_score', 'desc')
            ->orderBy('trend_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending offerings by location with hybrid scoring
     */
    public function getTrendingOfferings(
        string $userArea = null,
        string $timePeriod = 'daily',
        int $limit = 20
    ): \Illuminate\Database\Eloquent\Collection {
        $today = now()->format('Y-m-d');
        
        $query = TrendingData::where('item_type', 'offering')
            ->where('time_period', $timePeriod)
            ->where('date_period', $today)
            ->with('offering.business', 'offering.category');

        if ($userArea) {
            $query->where('location_area', $userArea);
        }

        return $query->orderBy('hybrid_score', 'desc')
            ->orderBy('trend_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearchTerms(int $limit = 20, ?int $categoryId = null): array
    {
        $query = SearchLog::query()
            ->whereNotNull('search_term')
            ->where('created_at', '>=', now()->subDays(30)) // Last 30 days
            ->selectRaw('search_term, COUNT(*) as search_count')
            ->groupBy('search_term');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->orderBy('search_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'search_term' => $item->search_term,
                    'search_count' => $item->search_count
                ];
            })
            ->toArray();
    }

    /**
     * Get start date for time period
     */
    private function getStartDate(string $timePeriod, string $date): string
    {
        $dateObj = \Carbon\Carbon::parse($date);
        
        return match($timePeriod) {
            'daily' => $dateObj->startOfDay()->format('Y-m-d H:i:s'),
            'weekly' => $dateObj->startOfWeek()->format('Y-m-d H:i:s'),
            'monthly' => $dateObj->startOfMonth()->format('Y-m-d H:i:s'),
            default => $dateObj->startOfDay()->format('Y-m-d H:i:s'),
        };
    }

    /**
     * Determine user area from latitude and longitude coordinates
     * Uses reverse geocoding logic for Bangladesh areas
     */


    /**
     * Log endpoint analytics for dashboard tracking
     */
    public function logEndpointAnalytics(
        string $endpoint,
        ?float $userLatitude = null,
        ?float $userLongitude = null,
        ?string $userArea = null,
        ?array $additionalData = null,
        Request $request = null
    ): \App\Models\EndpointAnalytics {
        return \App\Models\EndpointAnalytics::create([
            'endpoint' => $endpoint,
            'user_id' => Auth::id(),
            'user_area' => $userArea,
            'latitude' => $userLatitude,
            'longitude' => $userLongitude,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'additional_data' => $additionalData,
        ]);
    }

    /**
     * Track API endpoint usage with location data
     */
    public function trackEndpoint(
        string $endpointName,
        Request $request,
        ?array $additionalData = null
    ): void {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $userArea = null;

            // Determine user area if coordinates provided
            if ($latitude && $longitude) {
                $userArea = $this->locationService->determineUserAreaPrecise($latitude, $longitude);
            }

            $this->logEndpointAnalytics(
                endpoint: $endpointName,
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                userArea: $userArea,
                additionalData: $additionalData,
                request: $request
            );
        } catch (\Exception $e) {
            // Log error but don't break the main functionality
            Log::warning('Failed to track endpoint analytics: ' . $e->getMessage(), [
                'endpoint' => $endpointName,
                'request_url' => $request->fullUrl(),
            ]);
        }
    }
}
