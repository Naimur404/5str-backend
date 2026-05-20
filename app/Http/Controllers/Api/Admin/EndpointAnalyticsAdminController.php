<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\EndpointAnalyticsResource;
use App\Models\EndpointAnalytics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EndpointAnalyticsAdminController extends AdminResourceController
{
    protected static string $model = EndpointAnalytics::class;
    protected static string $resource = EndpointAnalyticsResource::class;
    protected static array $searchable = ['endpoint', 'user_area', 'ip_address'];
    protected static array $sortable = ['id', 'created_at'];
    protected static array $filters = [
        'endpoint' => 'endpoint',
        'user_id' => 'user_id',
        'user_area' => 'user_area',
    ];
    protected static array $with = ['user:id,name'];

    /**
     * Disable writes — endpoint analytics are produced by middleware only.
     */
    protected function validationRules(Request $request, ?Model $record = null): array
    {
        abort(405, 'Method not allowed on analytics data.');
    }

    public function store(Request $request): JsonResponse
    {
        abort(405, 'Method not allowed.');
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        abort(405, 'Method not allowed.');
    }

    /**
     * GET /api/admin/endpoint-analytics/summary
     * Aggregates: total requests, top endpoints, requests/hour for last 24h,
     * requests/day for last `days` (default 30).
     */
    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        $top = EndpointAnalytics::query()
            ->select('endpoint', DB::raw('COUNT(*) as request_count'))
            ->where('created_at', '>=', $since)
            ->groupBy('endpoint')
            ->orderByDesc('request_count')
            ->limit(20)
            ->get();

        $perDay = EndpointAnalytics::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as request_count'))
            ->where('created_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $perHourLast24 = EndpointAnalytics::query()
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as request_count'))
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $topAreas = EndpointAnalytics::query()
            ->select('user_area', DB::raw('COUNT(*) as request_count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_area')
            ->groupBy('user_area')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'total_requests' => EndpointAnalytics::where('created_at', '>=', $since)->count(),
                'requests_today' => EndpointAnalytics::whereDate('created_at', today())->count(),
                'top_endpoints' => $top,
                'per_day' => $perDay,
                'per_hour_last_24h' => $perHourLast24,
                'top_areas' => $topAreas,
            ],
        ]);
    }
}
