<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persists a SearchLog row whenever a request matches a search endpoint
 * and returns a 2xx JSON payload. Tracking failures never bubble up to
 * the request — they are logged as warnings.
 */
class AutoTrackSearches
{
    /**
     * Query-string flags that classify a request as a search.
     */
    private const SEARCH_PARAMS = ['search', 'q', 'query', 'search_term'];

    /**
     * Filter parameters extracted into the SearchLog payload.
     */
    private const FILTER_FIELDS = [
        'price_range', 'has_delivery', 'has_pickup', 'has_parking', 'is_verified',
    ];

    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 200 && $this->isSearchRequest($request)) {
            $this->trackSearch($request, $response);
        }

        return $response;
    }

    private function isSearchRequest(Request $request): bool
    {
        return $request->is('api/search*')
            || $request->is('api/businesses/search*')
            || $request->hasAny(self::SEARCH_PARAMS)
            || $request->routeIs('*search*');
    }

    private function trackSearch(Request $request, Response $response): void
    {
        try {
            $searchTerm = $request->input('search')
                ?? $request->input('q')
                ?? $request->input('query')
                ?? $request->input('search_term');

            $categoryId = $request->input('category_id') ?? $request->input('category');
            $latitude = $request->input('latitude') ?? $request->input('lat');
            $longitude = $request->input('longitude')
                ?? $request->input('lng')
                ?? $request->input('long');

            $filters = [];
            foreach (self::FILTER_FIELDS as $field) {
                if ($request->has($field)) {
                    $filters[$field] = $request->input($field);
                }
            }

            $this->analyticsService->logSearch(
                searchTerm: $searchTerm,
                categoryId: $categoryId !== null ? (int) $categoryId : null,
                userLatitude: $latitude !== null ? (float) $latitude : null,
                userLongitude: $longitude !== null ? (float) $longitude : null,
                filtersApplied: $filters !== [] ? $filters : null,
                resultsCount: $this->extractResultsCount($response),
            );
        } catch (\Throwable $e) {
            Log::warning('Auto search tracking failed: '.$e->getMessage());
        }
    }

    /**
     * Pull the canonical result count from the response. Prefers the
     * paginator `total` (the dataset size) over `count(data)` which is
     * the page size — that mismatch was producing wrong analytics.
     */
    private function extractResultsCount(Response $response): int
    {
        if (! $response instanceof JsonResponse) {
            return 0;
        }

        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return 0;
        }

        return (int) (
            $payload['data']['pagination']['total']
            ?? $payload['data']['meta']['total_count']
            ?? $payload['meta']['total']
            ?? $payload['meta']['total_count']
            ?? $payload['total']
            ?? (is_array($payload['data'] ?? null) ? count($payload['data']) : 0)
        );
    }
}
