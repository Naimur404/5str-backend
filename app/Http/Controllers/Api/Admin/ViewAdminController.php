<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\ViewResource;
use App\Models\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewAdminController extends AdminResourceController
{
    protected static string $model = View::class;
    protected static string $resource = ViewResource::class;
    protected static array $searchable = ['user_area', 'ip_address'];
    protected static array $sortable = ['id', 'created_at'];
    protected static array $filters = [
        'user_id' => 'user_id',
        'user_area' => 'user_area',
        'viewable_type' => 'viewable_type',
        'viewable_id' => 'viewable_id',
    ];
    protected static array $with = ['user:id,name'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        abort(405, 'Method not allowed on view logs.');
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
     * GET /api/admin/views/summary
     * Most-viewed targets, views per day, top areas.
     */
    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        $topTargets = View::query()
            ->select('viewable_type', 'viewable_id', DB::raw('COUNT(*) as view_count'))
            ->where('created_at', '>=', $since)
            ->groupBy('viewable_type', 'viewable_id')
            ->orderByDesc('view_count')
            ->limit(20)
            ->get();

        $perDay = View::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as view_count'))
            ->where('created_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topAreas = View::query()
            ->select('user_area', DB::raw('COUNT(*) as view_count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_area')
            ->groupBy('user_area')
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'total_views' => View::where('created_at', '>=', $since)->count(),
                'unique_viewers' => View::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
                'top_targets' => $topTargets,
                'per_day' => $perDay,
                'top_areas' => $topAreas,
            ],
        ]);
    }
}
