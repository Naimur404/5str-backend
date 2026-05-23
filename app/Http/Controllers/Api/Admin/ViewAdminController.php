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

    /**
     * Override baseQuery to eager-load the polymorphic `viewable`
     * with a per-type column whitelist via `morphWith`. The default
     * `static::$with` array can't carry a closure so we extend the
     * parent query here. The frontend uses `target_label` /
     * `target_name` from `ViewResource` for display, so this load
     * keeps the views list at O(1 query per touched morph type)
     * regardless of page size.
     */
    protected static function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::baseQuery()->with([
            'viewable' => function (\Illuminate\Database\Eloquent\Relations\MorphTo $morphTo) {
                $morphTo->morphWith([
                    \App\Models\Business::class => [],
                    \App\Models\BusinessOffering::class => ['business:id,business_name'],
                    \App\Models\Attraction::class => [],
                ]);
            },
        ]);
    }

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

        $topTargetsRaw = View::query()
            ->select('viewable_type', 'viewable_id', DB::raw('COUNT(*) as view_count'))
            ->where('created_at', '>=', $since)
            ->groupBy('viewable_type', 'viewable_id')
            ->orderByDesc('view_count')
            ->limit(20)
            ->get();

        $topTargets = $this->attachTargetLabels($topTargetsRaw);

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

    /**
     * Hydrate aggregate top-target rows with the human-readable name
     * of the target (business name, offering name, attraction name)
     * via one query per touched morph type rather than N+1.
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass|\Illuminate\Database\Eloquent\Model>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function attachTargetLabels(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        $byType = $rows->groupBy('viewable_type');

        $names = [];
        foreach ($byType as $type => $items) {
            $ids = $items->pluck('viewable_id')->all();

            if ($type === \App\Models\Business::class) {
                $names[$type] = \App\Models\Business::whereIn('id', $ids)->pluck('business_name', 'id')->all();
            } elseif ($type === \App\Models\BusinessOffering::class) {
                $offerings = \App\Models\BusinessOffering::with('business:id,business_name')
                    ->whereIn('id', $ids)
                    ->get(['id', 'name', 'business_id']);
                foreach ($offerings as $o) {
                    $names[$type][$o->id] = [
                        'name' => $o->name,
                        'parent' => $o->business?->business_name,
                    ];
                }
            } elseif ($type === \App\Models\Attraction::class) {
                $names[$type] = \App\Models\Attraction::whereIn('id', $ids)->pluck('name', 'id')->all();
            }
        }

        return $rows->map(function ($r) use ($names) {
            $type = $r->viewable_type ?? null;
            $id = $r->viewable_id ?? null;
            $shortType = $type ? strtolower(class_basename($type)) : 'unknown';

            $label = null;
            $parent = null;

            $entry = $names[$type][$id] ?? null;
            if (is_array($entry)) {
                $label = $entry['name'] ?? null;
                $parent = $entry['parent'] ?? null;
            } elseif (is_string($entry)) {
                $label = $entry;
            }

            return [
                'viewable_type' => $type,
                'viewable_id' => $id,
                'view_count' => (int) ($r->view_count ?? 0),
                'target' => [
                    'type' => $shortType,
                    'name' => $label,
                    'parent' => $parent,
                ],
            ];
        });
    }
}
