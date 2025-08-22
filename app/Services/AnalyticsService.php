<?php

namespace App\Services;

use App\Models\SearchLog;
use App\Models\View;
use App\Models\TrendingData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsService
{
    /**
     * Log a search query
     */
    public function logSearch(
        ?string $searchTerm = null,
        ?int $categoryId = null,
        ?float $userLatitude = null,
        ?float $userLongitude = null,
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
            'filters_applied' => $filtersApplied,
            'results_count' => $resultsCount,
            'clicked_business_id' => $clickedBusinessId,
        ]);
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
     * Calculate trending data for businesses
     */
    public function calculateBusinessTrending(string $timePeriod = 'daily', ?string $date = null): void
    {
        $date = $date ?: now()->format('Y-m-d');
        $startDate = $this->getStartDate($timePeriod, $date);
        
        // Get business search counts
        $businessSearches = SearchLog::query()
            ->whereNotNull('clicked_business_id')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('clicked_business_id, COUNT(*) as search_count')
            ->groupBy('clicked_business_id')
            ->get();

        // Get business view counts
        $businessViews = View::query()
            ->where('viewable_type', 'App\\Models\\Business')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('viewable_id, COUNT(*) as view_count')
            ->groupBy('viewable_id')
            ->get();

        // Combine data and calculate trend scores
        $businessData = [];
        
        foreach ($businessSearches as $search) {
            $businessData[$search->clicked_business_id] = [
                'searches' => $search->search_count,
                'views' => 0
            ];
        }

        foreach ($businessViews as $view) {
            if (!isset($businessData[$view->viewable_id])) {
                $businessData[$view->viewable_id] = ['searches' => 0, 'views' => 0];
            }
            $businessData[$view->viewable_id]['views'] = $view->view_count;
        }

        // Calculate and store trending data
        foreach ($businessData as $businessId => $data) {
            $business = \App\Models\Business::find($businessId);
            if ($business) {
                $trendScore = $this->calculateTrendScore($data['searches'], $data['views']);
                
                TrendingData::updateOrCreate([
                    'item_type' => 'business',
                    'item_id' => $businessId,
                    'time_period' => $timePeriod,
                    'date_period' => $date,
                    'location_area' => $business->area,
                ], [
                    'item_name' => $business->business_name,
                    'trend_score' => $trendScore,
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
}
