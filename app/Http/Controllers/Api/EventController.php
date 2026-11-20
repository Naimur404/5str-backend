<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Public event ingestion + per-user data erasure.
 *
 * Endpoints (mounted in `routes/api.php`):
 *
 *   POST /api/v1/events
 *     Bulk-ingest a batch of behaviour events from the mobile/web
 *     client. Optional auth — if a Sanctum token is present, events
 *     get tagged with the user_id; otherwise they're tagged only
 *     with the client's `anon_id` UUID.
 *
 *     Body shape:
 *       {
 *         "events": [
 *           {
 *             "anon_id": "uuid",       // required
 *             "event": "business_view", // required
 *             "target_type": "business",
 *             "target_id": 42,
 *             "lat_approx": 22.34,
 *             "lng_approx": 91.78,
 *             "source": "search_results",
 *             "payload": {...},
 *             "ts": "2026-05-23T10:32:01Z",
 *             "dedup_key": "..."
 *           },
 *           ...
 *         ],
 *         "platform": "android",
 *         "app_version": "2.1.0"
 *       }
 *
 *   POST /api/v1/me/data/clear
 *     Erase every event ever recorded for the caller's anon_id (and
 *     user_id, if authenticated). Required for Play data-safety
 *     "users can request deletion" disclosure.
 */
class EventController extends Controller
{
    public function __construct(private EventIngestionService $events) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.EventIngestionService::MAX_EVENTS_PER_REQUEST],
            'events.*' => ['array'],
            'events.*.anon_id' => ['required', 'uuid'],
            'events.*.event' => ['required', 'string', 'max:64'],
            'events.*.target_type' => ['nullable', 'string', 'max:64'],
            'events.*.target_id' => ['nullable', 'integer'],
            'events.*.category_id' => ['nullable', 'integer'],
            'events.*.lat_approx' => ['nullable', 'numeric'],
            'events.*.lng_approx' => ['nullable', 'numeric'],
            'events.*.latitude' => ['nullable', 'numeric'],
            'events.*.longitude' => ['nullable', 'numeric'],
            'events.*.user_area' => ['nullable', 'string', 'max:64'],
            'events.*.source' => ['nullable', 'string', 'max:32'],
            'events.*.payload' => ['nullable', 'array'],
            'events.*.session_id' => ['nullable', 'string', 'max:64'],
            'events.*.ts' => ['nullable', 'string'],
            'events.*.dedup_key' => ['nullable', 'string', 'max:96'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios', 'web'])],
            'app_version' => ['nullable', 'string', 'max:16'],
        ]);

        $accepted = $this->events->ingestBatch(
            batch: $data['events'],
            authenticatedUserId: optional($request->user())->id,
            platform: $data['platform'] ?? null,
            appVersion: $data['app_version'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'accepted' => $accepted,
                'submitted' => count($data['events']),
            ],
        ], 202);
    }

    /**
     * Wipe behavioural data for the calling identity.
     *
     * Authenticated callers wipe by user_id (so even rows the device
     * sent before login are caught). Anonymous callers wipe by their
     * client-supplied anon_id.
     */
    public function clearMyData(Request $request): JsonResponse
    {
        $request->validate([
            'anon_id' => ['nullable', 'uuid'],
        ]);

        $userId = optional($request->user())->id;
        $anonId = $request->input('anon_id');

        if (! $userId && ! $anonId) {
            return response()->json([
                'success' => false,
                'message' => 'Provide either an authenticated session or an anon_id.',
            ], 422);
        }

        $eventsDeleted = DB::table('user_events')
            ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
            ->when($anonId, fn ($q) => $q->orWhere('anon_id', $anonId))
            ->delete();

        $signalsDeleted = DB::table('user_signals')
            ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
            ->when($anonId, fn ($q) => $q->orWhere('anon_id', $anonId))
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'events_deleted' => $eventsDeleted,
                'signals_deleted' => $signalsDeleted,
            ],
        ]);
    }
}
