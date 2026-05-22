<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\AttractionSubmission;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\BusinessSubmission;
use App\Models\EndpointAnalytics;
use App\Models\OfferingSubmission;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\User;
use App\Models\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardAdminController extends Controller
{
    /**
     * GET /api/admin/dashboard/overview
     * Totals + % change vs previous period for headline cards.
     */
    public function overview(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $thisStart = now()->subDays($days);
        $prevStart = now()->subDays($days * 2);
        $prevEnd = $thisStart;

        $delta = function (int $current, int $previous): ?float {
            if ($previous === 0) {
                return $current > 0 ? 100.0 : 0.0;
            }
            return round(($current - $previous) / $previous * 100, 2);
        };

        $businessesThis = Business::where('created_at', '>=', $thisStart)->count();
        $businessesPrev = Business::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $usersThis = User::where('created_at', '>=', $thisStart)->count();
        $usersPrev = User::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $searchesThis = SearchLog::where('created_at', '>=', $thisStart)->count();
        $searchesPrev = SearchLog::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $viewsThis = View::where('created_at', '>=', $thisStart)->count();
        $viewsPrev = View::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $apiToday = EndpointAnalytics::whereDate('created_at', today())->count();
        $apiYesterday = EndpointAnalytics::whereDate('created_at', today()->subDay())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'totals' => [
                    'users' => User::count(),
                    'businesses' => Business::count(),
                    'attractions' => Attraction::count(),
                    'offerings' => BusinessOffering::count(),
                    'reviews' => Review::count(),
                ],
                'period' => [
                    'businesses_new' => $businessesThis,
                    'businesses_change_pct' => $delta($businessesThis, $businessesPrev),
                    'users_new' => $usersThis,
                    'users_change_pct' => $delta($usersThis, $usersPrev),
                    'searches' => $searchesThis,
                    'searches_change_pct' => $delta($searchesThis, $searchesPrev),
                    'views' => $viewsThis,
                    'views_change_pct' => $delta($viewsThis, $viewsPrev),
                ],
                'today' => [
                    'api_requests' => $apiToday,
                    'api_requests_yesterday' => $apiYesterday,
                    'api_change_pct' => $delta($apiToday, $apiYesterday),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/pending-approvals
     * Counts for moderation queues.
     */
    public function pendingApprovals(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'businesses' => [
                    'pending' => Business::where('approval_status', 'pending')->count(),
                    'approved' => Business::where('approval_status', 'approved')->count(),
                    'rejected' => Business::where('approval_status', 'rejected')->count(),
                ],
                'offerings' => [
                    'pending' => BusinessOffering::where('approval_status', 'pending')->count(),
                ],
                'submissions' => [
                    'businesses_pending' => BusinessSubmission::where('status', 'pending')->count(),
                    'attractions_pending' => AttractionSubmission::where('status', 'pending')->count(),
                    'offerings_pending' => OfferingSubmission::where('status', 'pending')->count(),
                ],
                'reviews' => [
                    'pending' => Review::where('status', 'pending')->count(),
                    'flagged' => Review::where('status', 'flagged')->count(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/business-growth?days=30
     * Daily new business count for the requested window.
     */
    public function businessGrowth(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        $perDay = Business::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'per_day' => $perDay,
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/area-usage?days=30
     * Search & view activity grouped by user_area.
     */
    public function areaUsage(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        $searches = SearchLog::query()
            ->select('user_area', DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_area')
            ->groupBy('user_area')
            ->orderByDesc('search_count')
            ->limit(20)
            ->get();

        $views = View::query()
            ->select('user_area', DB::raw('COUNT(*) as view_count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_area')
            ->groupBy('user_area')
            ->orderByDesc('view_count')
            ->limit(20)
            ->get();

        // Aggregate searches & views into a plain associative array.
        // PHP 8.4 forbids in-place mutation of array offsets accessed
        // through ArrayAccess (Collection), so we use an array here and
        // wrap it back into a Collection only when sorting.
        $combined = [];
        foreach ($searches as $row) {
            $combined[$row->user_area] = [
                'area' => (string) $row->user_area,
                'searches' => (int) $row->search_count,
                'views' => 0,
            ];
        }
        foreach ($views as $row) {
            if (! isset($combined[$row->user_area])) {
                $combined[$row->user_area] = [
                    'area' => (string) $row->user_area,
                    'searches' => 0,
                    'views' => (int) $row->view_count,
                ];
                continue;
            }
            $combined[$row->user_area]['views'] = (int) $row->view_count;
        }

        $sorted = collect($combined)
            ->sortByDesc(static fn (array $r) => $r['searches'] + $r['views'])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'areas' => $sorted,
            ],
        ]);
    }
}
