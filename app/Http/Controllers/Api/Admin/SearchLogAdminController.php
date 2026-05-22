<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\SearchLogResource;
use App\Models\SearchLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchLogAdminController extends AdminResourceController
{
    protected static string $model = SearchLog::class;
    protected static string $resource = SearchLogResource::class;
    protected static array $searchable = ['search_term', 'user_area'];
    protected static array $sortable = ['id', 'results_count', 'created_at'];
    protected static array $filters = [
        'user_id' => 'user_id',
        'user_area' => 'user_area',
        'category_id' => 'category_id',
        'clicked_business_id' => 'clicked_business_id',
    ];
    protected static array $with = ['user:id,name'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        abort(405, 'Method not allowed on search logs.');
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
     * GET /api/admin/search-logs/summary
     * Top search terms, top areas, zero-result queries, click-through.
     */
    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        $topTerms = SearchLog::query()
            ->select('search_term', DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('search_term')
            ->where('search_term', '!=', '')
            ->groupBy('search_term')
            ->orderByDesc('search_count')
            ->limit(20)
            ->get();

        $zeroResults = SearchLog::query()
            ->select('search_term', DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', $since)
            ->where('results_count', 0)
            ->whereNotNull('search_term')
            ->groupBy('search_term')
            ->orderByDesc('search_count')
            ->limit(20)
            ->get();

        $topAreas = SearchLog::query()
            ->select('user_area', DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_area')
            ->groupBy('user_area')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        $total = SearchLog::where('created_at', '>=', $since)->count();
        $clicks = SearchLog::where('created_at', '>=', $since)
            ->whereNotNull('clicked_business_id')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'total_searches' => $total,
                'click_through_count' => $clicks,
                'click_through_rate' => $total > 0 ? round($clicks / $total, 4) : 0,
                'top_terms' => $topTerms,
                'zero_result_terms' => $zeroResults,
                'top_areas' => $topAreas,
            ],
        ]);
    }
}
