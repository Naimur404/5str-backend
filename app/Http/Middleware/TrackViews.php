<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackViews
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

        // Only track successful GET requests
        if ($response->getStatusCode() === 200 && $request->isMethod('GET')) {
            $this->trackView($request);
        }

        return $response;
    }

    /**
     * Track views for specific routes
     */
    private function trackView(Request $request): void
    {
        try {
            $route = $request->route();
            if (!$route) return;

            $routeName = $route->getName();
            $parameters = $route->parameters();

            // Track business views
            if ($routeName === 'businesses.show' || str_contains($request->path(), 'businesses/')) {
                if (isset($parameters['business'])) {
                    $business = $parameters['business'];
                    if (is_object($business) && method_exists($business, 'getKey')) {
                        $this->analyticsService->logView($business, $request);
                    }
                }
            }

            // Track category views
            if ($routeName === 'categories.show' || str_contains($request->path(), 'categories/')) {
                if (isset($parameters['category'])) {
                    $category = $parameters['category'];
                    if (is_object($category) && method_exists($category, 'getKey')) {
                        $this->analyticsService->logView($category, $request);
                    }
                }
            }

        } catch (\Exception $e) {
            // Silently fail to not break the application
            Log::warning('View tracking failed: ' . $e->getMessage());
        }
    }
}
