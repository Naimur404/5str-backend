<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use App\Models\Business;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

class AutoTrackViews
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

        // Track views only for successful responses
        if ($response->getStatusCode() === 200) {
            $this->trackViewIfApplicable($request);
        }

        return $response;
    }

    private function trackViewIfApplicable(Request $request)
    {
        try {
            $route = Route::current();
            if (!$route) return;

            // Track business views
            if ($route->hasParameter('business')) {
                $business = $route->parameter('business');
                if ($business instanceof Business) {
                    $this->analyticsService->logView($business, $request);
                }
            }

            // Track business views by ID
            if ($route->hasParameter('id') && $request->is('*/businesses/*')) {
                $businessId = $route->parameter('id');
                $business = Business::find($businessId);
                if ($business) {
                    $this->analyticsService->logView($business, $request);
                }
            }

            // Track API business views
            if ($request->is('api/businesses/*')) {
                $segments = $request->segments();
                $businessIdIndex = array_search('businesses', $segments) + 1;
                if (isset($segments[$businessIdIndex])) {
                    $businessId = $segments[$businessIdIndex];
                    $business = Business::find($businessId);
                    if ($business) {
                        $this->analyticsService->logView($business, $request);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the request
            Log::warning('Auto view tracking failed: ' . $e->getMessage());
        }
    }
}
