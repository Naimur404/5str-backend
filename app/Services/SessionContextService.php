<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Short-term, in-memory view of "what is the user doing right now".
 *
 * Why this exists separately from UserSignals:
 *
 *   - UserSignals is rolled up hourly. That is too slow for "I just
 *     searched pizza, surface pizza places now". A fresh search
 *     should influence the next list within seconds, not the next
 *     hour.
 *
 *   - Putting per-event derived data in Redis (instead of replaying
 *     every event from the database on every request) keeps the
 *     recommender's hot path under 5ms.
 *
 * Storage shape (Redis):
 *   key:   "session_ctx:{anonId}"
 *   value: JSON {
 *     last_seen_at: 2026-05-23T10:11:12Z,
 *     events:       [ {event, target_type, target_id, category_id,
 *                      payload, ts}, ... ]   // most recent 20
 *     top_categories: { "12": 0.7, "5": 0.3 } // normalised weights
 *     top_brands:     { "207": 0.5, "84": 0.4 }
 *   }
 *
 * TTL: 30 minutes (aligned with typical mobile session length).
 *
 * The recommender BLENDS the long-term UserSignals with this
 * short-term context: 70% long-term, 30% session, scaling with
 * how much session signal we have.
 */
class SessionContextService
{
    public const MAX_EVENTS_TRACKED = 20;
    public const TTL_SECONDS = 1800; // 30 minutes
    public const KEY_PREFIX = 'session_ctx:';

    /**
     * Append one event to the session context. Idempotent on
     * (anon_id, event, target_id, ~minute) — duplicate writes are
     * silently dropped so the deduper at the ingest layer doesn't
     * have to know about us.
     */
    public function recordEvent(
        string $anonId,
        string $event,
        ?string $targetType,
        ?int $targetId,
        ?int $categoryId = null,
        ?array $payload = null,
    ): void {
        if (! $anonId || $anonId === '') {
            return;
        }

        try {
            $key = self::KEY_PREFIX . $anonId;
            $now = Carbon::now();

            // Pull existing context. Cache::lock would be safer for
            // strict consistency, but bumping a single user's
            // session is a low-stakes write — last writer wins is
            // fine here.
            $ctx = Cache::get($key);
            $ctx = is_array($ctx) ? $ctx : ['events' => []];

            $ctx['last_seen_at'] = $now->toIso8601String();
            $ctx['events'] ??= [];

            $ctx['events'][] = [
                'event' => $event,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'category_id' => $categoryId,
                'payload' => $payload,
                'ts' => $now->getTimestamp(),
            ];

            // Keep only the most recent N events.
            if (count($ctx['events']) > self::MAX_EVENTS_TRACKED) {
                $ctx['events'] = array_slice(
                    $ctx['events'],
                    -self::MAX_EVENTS_TRACKED,
                );
            }

            // Pre-aggregate top categories / brands so the
            // recommender doesn't have to recompute on every read.
            $ctx['top_categories'] = $this->aggregate($ctx['events'], 'category_id');
            $ctx['top_brands'] = $this->aggregate(
                $ctx['events'],
                'target_id',
                fn ($e) => ($e['target_type'] ?? null) === 'business',
            );

            Cache::put($key, $ctx, self::TTL_SECONDS);
        } catch (\Throwable $e) {
            // Session context is best-effort. Never break the
            // request because Redis hiccupped.
            Log::warning('Session context update failed', [
                'anon_id' => $anonId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read the current context for blending into the recommender.
     * Returns an empty context when the user has no session yet.
     *
     * @return array{events: array<int, array<string, mixed>>, top_categories: array<int, float>, top_brands: array<int, float>, has_signal: bool, last_seen_at: ?string}
     */
    public function readContext(?string $anonId): array
    {
        if (! $anonId) {
            return $this->emptyContext();
        }

        $ctx = Cache::get(self::KEY_PREFIX . $anonId);
        if (! is_array($ctx)) {
            return $this->emptyContext();
        }

        return [
            'events' => $ctx['events'] ?? [],
            'top_categories' => $ctx['top_categories'] ?? [],
            'top_brands' => $ctx['top_brands'] ?? [],
            'has_signal' => ! empty($ctx['events']),
            'last_seen_at' => $ctx['last_seen_at'] ?? null,
        ];
    }

    /**
     * Hydrate context for a brand-new request from the most recent
     * persisted events (last 30 minutes). Lets the recommender
     * benefit from session context even if the cache was cold.
     */
    public function bootstrapFromDb(string $anonId): void
    {
        try {
            $events = UserEvent::query()
                ->where('anon_id', $anonId)
                ->where('created_at', '>=', Carbon::now()->subMinutes(30))
                ->orderByDesc('id')
                ->limit(self::MAX_EVENTS_TRACKED)
                ->get(['event', 'target_type', 'target_id', 'category_id', 'created_at']);

            if ($events->isEmpty()) {
                return;
            }

            // Replay through the normal recordEvent path so the
            // aggregation logic stays in one place.
            foreach ($events->reverse() as $e) {
                $this->recordEvent(
                    anonId: $anonId,
                    event: (string) $e->event,
                    targetType: $e->target_type,
                    targetId: $e->target_id,
                    categoryId: $e->category_id,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Session bootstrap failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Compute weighted scores over the events list. Recency-decayed:
     * the most recent event counts 1.0, the 20th-most-recent counts
     * 1/20.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  callable(array<string, mixed>): bool|null  $filter
     * @return array<int, float>
     */
    private function aggregate(array $events, string $field, ?callable $filter = null): array
    {
        $scores = [];
        $count = count($events);
        if ($count === 0) return [];

        // Walk newest → oldest. Newest event (index $count-1) gets
        // weight 1; oldest gets weight 1/$count.
        foreach (array_reverse($events) as $i => $e) {
            if ($filter !== null && ! $filter($e)) continue;
            $value = $e[$field] ?? null;
            if ($value === null) continue;
            $weight = 1.0 / ($i + 1);
            $key = (int) $value;
            $scores[$key] = ($scores[$key] ?? 0.0) + $weight;
        }

        if (empty($scores)) return [];

        // Normalise so weights sum to 1 across the top 5.
        arsort($scores);
        $scores = array_slice($scores, 0, 5, true);
        $total = array_sum($scores);
        if ($total <= 0) return [];

        $normalised = [];
        foreach ($scores as $k => $v) {
            $normalised[$k] = round($v / $total, 4);
        }
        return $normalised;
    }

    /**
     * @return array{events: list<array<string, mixed>>, top_categories: array<int, float>, top_brands: array<int, float>, has_signal: bool, last_seen_at: null}
     */
    private function emptyContext(): array
    {
        return [
            'events' => [],
            'top_categories' => [],
            'top_brands' => [],
            'has_signal' => false,
            'last_seen_at' => null,
        ];
    }
}
