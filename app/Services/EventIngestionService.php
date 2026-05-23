<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Validation, sanitisation, and bulk insert for the public
 * `POST /api/v1/events` endpoint.
 *
 * Why a service:
 *   - Keeps the controller thin and testable.
 *   - Centralises the safety rules: max events per request, payload
 *     size cap, allowed event vocabulary, location rounding, dedup.
 *   - Idempotent inserts via `INSERT IGNORE` on `dedup_key` so a
 *     network retry from the device never double-records.
 *
 * Privacy posture (Play-policy safe):
 *   - Locations are forcibly rounded to 2 decimals (~1km) on the
 *     server side too, so even a misbehaving client can't stash
 *     precise GPS history.
 *   - We never read `request()->ip()`. We never store `User-Agent`.
 *     The only identifier is the client-generated `anon_id` UUID,
 *     plus `user_id` if the user happens to be logged in.
 *   - The `payload` JSON field is bounded to 4KB per event to keep
 *     row size predictable and prevent abuse.
 */
class EventIngestionService
{
    /** Hard cap on events per request — anything beyond is rejected. */
    public const MAX_EVENTS_PER_REQUEST = 100;

    /** Hard cap on serialized payload size per event. */
    public const MAX_PAYLOAD_BYTES = 4096;

    /**
     * Allowed event names. Anything outside this set is silently
     * dropped (we don't 4xx because the client has already moved on
     * — better to emit metrics about unknown events than to fail).
     *
     * Add new events here as the recommender starts to consume them.
     *
     * @var list<string>
     */
    public const ALLOWED_EVENTS = [
        'home_open',
        'business_view',
        'business_click',
        'attraction_view',
        'attraction_click',
        'offering_view',
        'offer_view',
        'offer_used',
        'category_open',
        'search',
        'search_click',
        'favorite_added',
        'favorite_removed',
        'collection_added',
        'review_submitted',
        'recommendation_impression',
        'recommendation_click',
        'recommendation_dismiss',
        'banner_click',
        'share',
    ];

    /**
     * Allowed `target_type` slugs sent by the client. Mapped here so
     * the column stays compact strings instead of fully-qualified
     * class names; the recommender resolves them to model classes
     * when needed.
     *
     * @var list<string>
     */
    public const ALLOWED_TARGET_TYPES = [
        'business',
        'attraction',
        'offering',
        'offer',
        'category',
        'collection',
        'review',
        'banner',
        'search_term',
    ];

    /**
     * Ingest a batch of events. Returns the number of rows inserted
     * (after dedup and validation drops).
     *
     * @param  array<int, array<string, mixed>>  $batch
     */
    public function ingestBatch(
        array $batch,
        ?int $authenticatedUserId,
        ?string $platform = null,
        ?string $appVersion = null,
    ): int {
        if (empty($batch)) {
            return 0;
        }

        if (count($batch) > self::MAX_EVENTS_PER_REQUEST) {
            $batch = array_slice($batch, 0, self::MAX_EVENTS_PER_REQUEST);
        }

        $now = Carbon::now();
        $rows = [];
        $seenDedupKeys = [];

        foreach ($batch as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $event = $this->sanitiseEvent($raw, $authenticatedUserId, $platform, $appVersion, $now);
            if ($event === null) {
                continue;
            }

            // De-duplicate within the batch itself before letting the
            // unique index do the cross-batch dedup.
            if ($event['dedup_key'] !== null) {
                if (isset($seenDedupKeys[$event['dedup_key']])) {
                    continue;
                }
                $seenDedupKeys[$event['dedup_key']] = true;
            }

            $rows[] = $event;
        }

        if (empty($rows)) {
            return 0;
        }

        try {
            // INSERT IGNORE so a duplicate dedup_key from a network
            // retry doesn't error — just silently drops the dupe.
            DB::table('user_events')->insertOrIgnore($rows);
        } catch (\Throwable $e) {
            Log::warning('Failed to ingest events batch', [
                'count' => count($rows),
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        return count($rows);
    }

    /**
     * Validate one raw event dict and return the row payload, or
     * null if it should be dropped.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function sanitiseEvent(
        array $raw,
        ?int $authenticatedUserId,
        ?string $platform,
        ?string $appVersion,
        Carbon $now,
    ): ?array {
        $anonId = isset($raw['anon_id']) && is_string($raw['anon_id']) ? $raw['anon_id'] : null;
        if (! $anonId || ! Str::isUuid($anonId)) {
            return null;
        }

        $eventName = isset($raw['event']) && is_string($raw['event']) ? $raw['event'] : null;
        if (! $eventName || ! in_array($eventName, self::ALLOWED_EVENTS, true)) {
            return null;
        }

        $targetType = null;
        if (isset($raw['target_type']) && is_string($raw['target_type'])
            && in_array($raw['target_type'], self::ALLOWED_TARGET_TYPES, true)) {
            $targetType = $raw['target_type'];
        }

        $targetId = $this->intOrNull($raw['target_id'] ?? null);
        $categoryId = $this->intOrNull($raw['category_id'] ?? null);

        // Force-round locations to 2 decimals (~1km) on the server side
        // even if the client forgot. Defence in depth.
        $latApprox = $this->roundedLocation($raw['lat_approx'] ?? $raw['latitude'] ?? null, 2);
        $lngApprox = $this->roundedLocation($raw['lng_approx'] ?? $raw['longitude'] ?? null, 2);

        $userArea = isset($raw['user_area']) && is_string($raw['user_area'])
            ? mb_substr($raw['user_area'], 0, 64)
            : null;

        $source = isset($raw['source']) && is_string($raw['source'])
            ? mb_substr($raw['source'], 0, 32)
            : null;

        $sessionId = isset($raw['session_id']) && is_string($raw['session_id'])
            ? mb_substr($raw['session_id'], 0, 64)
            : null;

        // Payload: drop anything bigger than the cap.
        $payload = null;
        if (isset($raw['payload']) && is_array($raw['payload'])) {
            $encoded = json_encode($raw['payload'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($encoded !== false && strlen($encoded) <= self::MAX_PAYLOAD_BYTES) {
                $payload = $encoded;
            }
        }

        // Dedup key: client-supplied first; otherwise derive from the
        // event identity so a single retry of the same logical action
        // is idempotent.
        $dedupKey = isset($raw['dedup_key']) && is_string($raw['dedup_key']) && $raw['dedup_key'] !== ''
            ? mb_substr($raw['dedup_key'], 0, 96)
            : $this->deriveDedupKey($anonId, $eventName, $targetType, $targetId, $raw['ts'] ?? null);

        $clientTs = $this->parseClientTimestamp($raw['ts'] ?? null) ?? $now;

        return [
            'anon_id' => $anonId,
            'user_id' => $authenticatedUserId,
            'event' => mb_substr($eventName, 0, 64),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'category_id' => $categoryId,
            'lat_approx' => $latApprox,
            'lng_approx' => $lngApprox,
            'user_area' => $userArea,
            'source' => $source,
            'payload' => $payload,
            'session_id' => $sessionId,
            'app_version' => $appVersion ? mb_substr($appVersion, 0, 16) : null,
            'platform' => $platform ? mb_substr($platform, 0, 16) : null,
            'dedup_key' => $dedupKey,
            'created_at' => $clientTs,
        ];
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === false) {
            return null;
        }
        return is_numeric($v) ? (int) $v : null;
    }

    private function roundedLocation(mixed $v, int $places): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        return round((float) $v, $places);
    }

    private function deriveDedupKey(
        string $anonId,
        string $event,
        ?string $targetType,
        ?int $targetId,
        mixed $clientTs,
    ): string {
        $bucket = $this->parseClientTimestamp($clientTs)?->floorMinute()->getTimestamp() ?? Carbon::now()->floorMinute()->getTimestamp();
        return substr(hash('sha256', sprintf(
            '%s|%s|%s|%s|%d',
            $anonId,
            $event,
            $targetType ?? '-',
            (string) ($targetId ?? '-'),
            $bucket,
        )), 0, 64);
    }

    private function parseClientTimestamp(mixed $v): ?Carbon
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        try {
            $ts = Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }

        // Reject obviously bad clocks (more than 1 day in the future
        // or more than 30 days in the past). Stops device clock skew
        // from polluting time-series analytics.
        if ($ts->isAfter(Carbon::now()->addDay()) || $ts->isBefore(Carbon::now()->subDays(30))) {
            return null;
        }

        return $ts;
    }
}
