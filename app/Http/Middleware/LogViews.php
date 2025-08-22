<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\AnalyticsService;

class LogViews
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

        // Only log successful responses
        if ($response->getStatusCode() === 200) {
            $this->logViewIfApplicable($request);
        }

        return $response;
    }

    /**
     * Log view for specific routes/models
     */
    private function logViewIfApplicable(Request $request): void
    {
        $route = $request->route();
        if (!$route) return;

        $routeName = $route->getName();
        $parameters = $route->parameters();

        // Log business views
        if (str_contains($routeName, 'business') && isset($parameters['business'])) {
            $business = $parameters['business'];
            if ($business instanceof \App\Models\Business) {
                $this->analyticsService->logView($business, $request);
            }
        }

        // Log business offering views
        if (str_contains($routeName, 'offering') && isset($parameters['offering'])) {
            $offering = $parameters['offering'];
            if ($offering instanceof \App\Models\BusinessOffering) {
                $this->analyticsService->logView($offering, $request);
            }
        }

        // Log category views
        if (str_contains($routeName, 'category') && isset($parameters['category'])) {
            $category = $parameters['category'];
            if ($category instanceof \App\Models\Category) {
                $this->analyticsService->logView($category, $request);
            }
        }

        // Log offer views
        if (str_contains($routeName, 'offer') && isset($parameters['offer'])) {
            $offer = $parameters['offer'];
            if ($offer instanceof \App\Models\Offer) {
                $this->analyticsService->logView($offer, $request);
            }
        }
    }
}
