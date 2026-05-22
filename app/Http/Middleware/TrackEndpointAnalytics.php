<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persists per-endpoint usage analytics for any 2xx response.
 *
 * The endpoint name is derived from `controller@method` when available,
 * falling back to the URL path so any new route is captured automatically
 * without code changes. Hot path: keep this lean — a hashmap and one
 * AnalyticsService::trackEndpoint call per request.
 */
class TrackEndpointAnalytics
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/healthz', 'api/readyz', 'api/horizon/*')) {
            return $next($request);
        }

        $startedAt = microtime(true);

        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $route = $request->route();
        if (! $route instanceof Route) {
            return $response;
        }

        $endpointName = $this->resolveEndpointName($route, $request);
        if ($endpointName === null) {
            return $response;
        }

        $this->analyticsService->trackEndpoint(
            $endpointName,
            $request,
            [
                'status_code' => $status,
                'method' => $request->getMethod(),
                'response_time' => round((microtime(true) - $startedAt) * 1000, 2),
                'has_auth' => $request->user() !== null,
                'route_name' => $route->getName(),
            ],
        );

        return $response;
    }

    /**
     * Derive a stable, low-cardinality endpoint identifier:
     * 1. controller-method  (Api\\BusinessController@show -> business_show)
     * 2. URL path           (api/v1/categories/main      -> categories_main)
     */
    private function resolveEndpointName(Route $route, Request $request): ?string
    {
        $action = $route->getActionName();
        if ($action !== 'Closure' && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $base = preg_replace('/Controller$/', '', class_basename($controller));
            if ($base !== null && $base !== '') {
                return $this->snake($base).'_'.$this->snake($method);
            }
        }

        $path = $request->path();
        if (! str_starts_with($path, 'api/')) {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $s) => $s !== '' && $s !== 'api' && $s !== 'v1' && ! ctype_digit($s),
        ));

        if ($segments === []) {
            return null;
        }

        return implode('_', array_map($this->snake(...), $segments));
    }

    private function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)([A-Z])/', '_$1', $value) ?? $value;

        return strtolower(str_replace('-', '_', $value));
    }
}
