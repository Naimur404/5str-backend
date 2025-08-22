<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackSearches
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Track search requests
        if ($response->getStatusCode() === 200 && $this->isSearchRequest($request)) {
            $this->trackSearch($request, $response);
        }

        return $response;
    }

    /**
     * Determine if this is a search request
     */
    private function isSearchRequest(Request $request): bool
    {
        $path = $request->path();
        $query = $request->query();

        return (
            str_contains($path, 'search') ||
            str_contains($path, 'businesses') && $request->isMethod('GET') && !empty($query) ||
            $request->has(['search', 'q', 'term', 'query']) ||
            ($request->has('category_id') && $request->isMethod('GET'))
        );
    }

    /**
     * Track the search
     */
    private function trackSearch(Request $request, Response $response): void
    {
        try {
            // Extract search parameters
            $searchTerm = $request->input('search') ?? 
                         $request->input('q') ?? 
                         $request->input('term') ?? 
                         $request->input('query');

            $categoryId = $request->input('category_id');
            $latitude = $request->input('latitude') ?? $request->input('lat');
            $longitude = $request->input('longitude') ?? $request->input('lng');

            // Extract filters
            $filters = [];
            if ($request->has('price_range')) $filters['price_range'] = $request->input('price_range');
            if ($request->has('has_delivery')) $filters['has_delivery'] = $request->boolean('has_delivery');
            if ($request->has('has_pickup')) $filters['has_pickup'] = $request->boolean('has_pickup');
            if ($request->has('has_parking')) $filters['has_parking'] = $request->boolean('has_parking');
            if ($request->has('is_verified')) $filters['is_verified'] = $request->boolean('is_verified');
            if ($request->has('area')) $filters['area'] = $request->input('area');
            if ($request->has('city')) $filters['city'] = $request->input('city');

            // Try to get results count from response
            $resultsCount = 0;
            if ($response->headers->get('content-type') === 'application/json') {
                $content = json_decode($response->getContent(), true);
                if (isset($content['data']) && is_array($content['data'])) {
                    $resultsCount = count($content['data']);
                } elseif (isset($content['total'])) {
                    $resultsCount = (int) $content['total'];
                } elseif (is_array($content)) {
                    $resultsCount = count($content);
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
            // Silently fail to not break the application
            Log::warning('Search tracking failed: ' . $e->getMessage());
        }
    }
}
