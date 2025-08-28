<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use Symfony\Component\HttpFoundation\Response;

class TrackEndpointAnalytics
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

        // Only track successful API requests
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            // Extract endpoint name from route
            $route = $request->route();
            if ($route) {
                $endpointName = $this->getEndpointName($route, $request);
                
                if ($endpointName) {
                    // Gather additional data for analytics
                    $additionalData = [
                        'status_code' => $response->getStatusCode(),
                        'method' => $request->method(),
                        'response_time' => microtime(true) - LARAVEL_START,
                        'has_auth' => $request->user() !== null,
                        'route_name' => $route->getName(),
                    ];

                    // Track the endpoint
                    $this->analyticsService->trackEndpoint(
                        $endpointName,
                        $request,
                        $additionalData
                    );
                }
            }
        }

        return $response;
    }

    /**
     * Determine endpoint name from route
     */
    private function getEndpointName($route, Request $request): ?string
    {
        $routeName = $route->getName();
        $actionName = $route->getActionName();
        
        // Map route names to endpoint analytics names
        $endpointMap = [
            // Home endpoints
            'home.index' => 'home_index',
            'home.featured-sections' => 'featured_sections',
            'home.statistics' => 'statistics',
            'home.trending' => 'trending',
            'home.today-trending' => 'today_trending',
            'home.top-rated' => 'top_rated',
            'home.open-now' => 'open_now',
            'home.top-services' => 'top_services',
            'home.popular-nearby' => 'popular_nearby',
            
            // Business endpoints
            'businesses.index' => 'business_index',
            'businesses.show' => 'business_show',
            'businesses.reviews' => 'business_reviews',
            'businesses.analytics.area' => 'business_analytics_area',
            'businesses.analytics.precise-area' => 'business_analytics_precise_area',
            'businesses.analytics.area-comparison' => 'business_area_comparison',
            
            // Search endpoints
            'search.businesses' => 'search_businesses',
            'search.offerings' => 'search_offerings',
            'search.analytics.area' => 'search_analytics_area',
            'search.analytics.trends' => 'search_trends',
            
            // Offering endpoints
            'offerings.index' => 'offering_index',
            'offerings.show' => 'offering_show',
            'offerings.reviews' => 'offering_reviews',
            'offerings.analytics.area-analytics' => 'offering_analytics_area',
            'offerings.analytics.area-offerings' => 'offering_area_search',
            'offerings.analytics.popular-in-area' => 'offering_popular_area',
        ];

        // Check by route name first
        if ($routeName && isset($endpointMap[$routeName])) {
            return $endpointMap[$routeName];
        }

        // Fallback: extract from action name
        if (str_contains($actionName, 'HomeController@index')) {
            return 'home_index';
        } elseif (str_contains($actionName, 'BusinessController@index')) {
            return 'business_index';
        } elseif (str_contains($actionName, 'BusinessController@show')) {
            return 'business_show';
        } elseif (str_contains($actionName, 'SearchController@searchBusinesses')) {
            return 'search_businesses';
        } elseif (str_contains($actionName, 'SearchController@searchOfferings')) {
            return 'search_offerings';
        } elseif (str_contains($actionName, 'OfferingController@index')) {
            return 'offering_index';
        } elseif (str_contains($actionName, 'OfferingController@show')) {
            return 'offering_show';
        }

        // Extract from URL path as last resort
        $path = $request->path();
        if (str_starts_with($path, 'api/v1/')) {
            $segments = explode('/', $path);
            if (count($segments) >= 3) {
                return str_replace('-', '_', $segments[2]) . '_' . ($segments[3] ?? 'index');
            }
        }

        return null;
    }
}
