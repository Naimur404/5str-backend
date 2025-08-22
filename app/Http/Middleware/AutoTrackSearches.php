<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;

class AutoTrackSearches
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Track searches only for successful responses and search-related endpoints
        if ($response->getStatusCode() === 200 && $this->isSearchRequest($request)) {
            $this->trackSearch($request, $response);
        }

        return $response;
    }

    private function isSearchRequest(Request $request): bool
    {
        // Check if this is a search-related request
        return $request->is('api/search*') || 
               $request->is('api/businesses/search*') ||
               $request->has(['search', 'q', 'query', 'search_term']) ||
               $request->routeIs('*search*');
    }

    private function trackSearch(Request $request, $response)
    {
        try {
            // Extract search parameters
            $searchTerm = $request->input('search') ?? 
                         $request->input('q') ?? 
                         $request->input('query') ?? 
                         $request->input('search_term');

            $categoryId = $request->input('category_id') ?? 
                         $request->input('category');

            $latitude = $request->input('latitude') ?? 
                       $request->input('lat');

            $longitude = $request->input('longitude') ?? 
                        $request->input('lng') ?? 
                        $request->input('long');

            // Extract filters
            $filters = [];
            $filterFields = ['price_range', 'has_delivery', 'has_pickup', 'has_parking', 'is_verified'];
            foreach ($filterFields as $field) {
                if ($request->has($field)) {
                    $filters[$field] = $request->input($field);
                }
            }

            // Get results count from response
            $resultsCount = 0;
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = $response->getData(true);
                if (isset($data['data']) && is_array($data['data'])) {
                    $resultsCount = count($data['data']);
                } elseif (isset($data['businesses']) && is_array($data['businesses'])) {
                    $resultsCount = count($data['businesses']);
                } elseif (is_array($data)) {
                    $resultsCount = count($data);
                }
            }

            // Log the search
            $this->analyticsService->logSearch(
                searchTerm: $searchTerm,
                categoryId: $categoryId ? (int) $categoryId : null,
                userLatitude: $latitude ? (float) $latitude : null,
                userLongitude: $longitude ? (float) $longitude : null,
                filtersApplied: !empty($filters) ? $filters : null,
                resultsCount: $resultsCount
            );

        } catch (\Exception $e) {
            // Log error but don't break the request
            Log::warning('Auto search tracking failed: ' . $e->getMessage());
        }
    }
}
