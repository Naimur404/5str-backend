<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs business views post-response. Uses the route-bound Business
 * instance when present so we never re-query the DB just to track a view.
 */
class AutoTrackViews
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/healthz', 'api/readyz', 'api/horizon/*')) {
            return $next($request);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            $this->trackViewIfApplicable($request);
        }

        return $response;
    }

    private function trackViewIfApplicable(Request $request): void
    {
        try {
            $route = $request->route();
            if (! $route) {
                return;
            }

            $bound = $route->parameter('business');
            if ($bound instanceof Business) {
                $this->analyticsService->logView($bound, $request);

                return;
            }

            // Fallback: scalar id in path segments — track without an extra
            // model fetch by deferring the lookup to AnalyticsService.
            if ($request->is('api/businesses/*')) {
                $segments = $request->segments();
                $idx = array_search('businesses', $segments, true);
                if ($idx !== false && isset($segments[$idx + 1]) && ctype_digit($segments[$idx + 1])) {
                    $business = Business::find((int) $segments[$idx + 1]);
                    if ($business) {
                        $this->analyticsService->logView($business, $request);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Auto view tracking failed: '.$e->getMessage());
        }
    }
}
