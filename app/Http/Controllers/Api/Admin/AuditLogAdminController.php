<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only admin view of the activity_log table populated by the
 * `AuditableByAdmin` trait. Records are auto-pruned after 90 days
 * by the daily `activitylog:clean` schedule (see `config/activitylog.php`
 * and `routes/console.php`), so this controller stays read-only.
 *
 * Filters supported on the index endpoint:
 *   ?subject_type=App\Models\Business
 *   ?subject_id=42
 *   ?causer_id=7
 *   ?event=updated
 *   ?log_name=audit
 *   ?since=2026-05-01    (any strtotime-compatible string)
 *   ?until=2026-05-23
 *   ?q=...               (LIKE on description)
 */
class AuditLogAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 200);

        $query = Activity::query()
            ->with([
                'causer' => function ($q) {
                    // Most causers are users; eagerly select id+name+email.
                    $q->select('id', 'name', 'email');
                },
            ])
            ->orderByDesc('id');

        if ($v = $request->input('subject_type')) {
            $query->where('subject_type', $v);
        }
        if ($v = $request->input('subject_id')) {
            $query->where('subject_id', (int) $v);
        }
        if ($v = $request->input('causer_id')) {
            $query->where('causer_id', (int) $v);
        }
        if ($v = $request->input('event')) {
            $query->where('event', $v);
        }
        if ($v = $request->input('log_name')) {
            $query->where('log_name', $v);
        }
        if ($v = $request->input('since')) {
            $query->where('created_at', '>=', $this->parseDate($v));
        }
        if ($v = $request->input('until')) {
            $query->where('created_at', '<=', $this->parseDate($v));
        }
        if ($v = trim((string) $request->input('q', ''))) {
            $query->where('description', 'like', "%{$v}%");
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map(fn (Activity $a) => $this->transform($a))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(int|string $id): JsonResponse
    {
        $activity = Activity::query()
            ->with(['causer:id,name,email'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->transform($activity, true),
        ]);
    }

    /**
     * GET /api/admin/audit-logs/summary
     * Aggregates over the last `days` window (default 30) for sparkline / cards.
     */
    public function summary(Request $request): JsonResponse
    {
        $days = max(1, (int) $request->input('days', 30));
        $since = now()->subDays($days);

        $base = Activity::query()->where('created_at', '>=', $since);

        $byEvent = (clone $base)
            ->selectRaw('event, COUNT(*) as event_count')
            ->groupBy('event')
            ->orderByDesc('event_count')
            ->get();

        $bySubjectType = (clone $base)
            ->selectRaw('subject_type, COUNT(*) as activity_count')
            ->whereNotNull('subject_type')
            ->groupBy('subject_type')
            ->orderByDesc('activity_count')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'subject_type' => $this->shortType($r->subject_type),
                'subject_type_full' => $r->subject_type,
                'activity_count' => (int) $r->activity_count,
            ]);

        $topCausers = (clone $base)
            ->selectRaw('causer_id, COUNT(*) as activity_count')
            ->whereNotNull('causer_id')
            ->groupBy('causer_id')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->get();

        $perDay = (clone $base)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as activity_count')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'window_days' => $days,
                'total_activities' => (clone $base)->count(),
                'by_event' => $byEvent,
                'by_subject_type' => $bySubjectType,
                'top_causers' => $topCausers,
                'per_day' => $perDay,
                'retention_days' => (int) config('activitylog.clean_after_days', 90),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Activity $activity, bool $verbose = false): array
    {
        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'subject_type' => $activity->subject_type,
            'subject_type_short' => $this->shortType($activity->subject_type),
            'subject_id' => $activity->subject_id,
            'causer' => $activity->causer ? [
                'id' => $activity->causer->id ?? null,
                'name' => $activity->causer->name ?? null,
                'email' => $activity->causer->email ?? null,
            ] : null,
            'causer_id' => $activity->causer_id,
            'causer_type' => $activity->causer_type,
            // The "properties" column carries `attributes` (the new
            // values) and `old` (the previous values). For the index
            // listing we only return a count to keep payload small;
            // the show endpoint emits the full diff.
            'changes_count' => $this->countChanges($activity),
            'properties' => $verbose ? $activity->properties?->toArray() : null,
            'attribute_changes' => $verbose ? $activity->attribute_changes?->toArray() : null,
            'created_at' => $activity->created_at,
        ];
    }

    private function countChanges(Activity $activity): int
    {
        $props = $activity->properties;
        if (! $props) return 0;
        $arr = $props->toArray();
        $attributes = $arr['attributes'] ?? [];
        return is_array($attributes) ? count($attributes) : 0;
    }

    private function shortType(?string $type): ?string
    {
        if (! $type) return null;
        $parts = explode('\\', $type);
        return end($parts) ?: $type;
    }

    private function parseDate(string $value): \Illuminate\Support\Carbon
    {
        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }
}
