<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\UserEvent;
use App\Models\UserSignals;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Roll user_events into compact per-identity affinity signals.
 *
 * Reads the trailing 30 days of events for an `anon_id` (and the
 * mapped `user_id` if known), computes weighted top-N category /
 * area / brand affinities, plus simple time-of-day distribution,
 * and upserts a single row in `user_signals`.
 *
 * Scheduling:
 *   `signals:rebuild --since=hour` runs hourly via routes/console.php
 *   to refresh anyone with new events in the last hour.
 *   `signals:rebuild --all` is the catchup path (invoke once after
 *   deploy if user_signals is cold).
 *
 * Recency weighting:
 *   Each event contributes `weight × time_decay`. Time decay halves
 *   every 7 days. Weights per event type favour explicit positive
 *   actions (favorite_added, offer_used, review_submitted) over
 *   passive views.
 *
 * Why this isn't pushed onto the recommender at request time:
 *   30 days × millions of events × per-request scan = death. With
 *   this rollup, the recommender does a single indexed read.
 */
class UserSignalsService
{
    /** Lookback window for the rollup. */
    private const LOOKBACK_DAYS = 30;

    /** Half-life for time decay. */
    private const HALF_LIFE_DAYS = 7;

    /** Top-N retained per dimension (categories, areas, brands). */
    private const TOP_N = 8;

    /** Cold-start threshold — fewer events than this and we still
     *  store the row but mark it `cold` so the recommender knows
     *  not to over-personalise. */
    private const COLD_THRESHOLD = 5;

    /**
     * Per-event weights. Higher = stronger signal of intent.
     *
     * @var array<string, float>
     */
    private const EVENT_WEIGHTS = [
        'business_view'        => 1.0,
        'attraction_view'      => 1.0,
        'offering_view'        => 1.0,
        'business_click'       => 1.5,
        'attraction_click'     => 1.5,
        'category_open'        => 1.2,
        'search'               => 0.8,
        'search_click'         => 1.6,
        'recommendation_click' => 1.7,
        'banner_click'         => 0.5,
        'favorite_added'       => 4.0,
        'favorite_removed'     => -1.0,
        'collection_added'     => 3.0,
        'review_submitted'     => 5.0,
        'offer_view'           => 1.0,
        'offer_used'           => 4.0,
        'share'                => 2.5,
    ];

    /**
     * Refresh signals for every identity that has any new event in
     * the given window. Returns the number of rows upserted.
     *
     * @param  string  $since  '1 hour' | '6 hours' | '1 day' | 'all'
     */
    public function refreshAll(string $since = '1 hour'): int
    {
        $cutoff = $since === 'all' ? null : $this->parseSince($since);

        $identities = $this->identitiesNeedingRefresh($cutoff);
        $count = 0;

        foreach ($identities as $identity) {
            try {
                $this->refreshOne($identity['anon_id'], $identity['user_id']);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('Failed to refresh user signals', [
                    'anon_id' => $identity['anon_id'] ?? null,
                    'user_id' => $identity['user_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Recompute signals for a single identity. Either anon_id or
     * user_id may be null. If both are present, signals are stored
     * under user_id (logged-in trumps anonymous).
     */
    public function refreshOne(?string $anonId, ?int $userId): void
    {
        if (! $anonId && ! $userId) {
            return;
        }

        $events = $this->loadEvents($anonId, $userId);
        if ($events->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        $payload = [
            'top_categories' => $this->topCategories($events, $now),
            'top_areas' => $this->topAreas($events, $now),
            'top_brands' => $this->topBrands($events, $now),
            'price_range' => $this->dominantPriceRange($events),
            'time_of_day' => $this->timeOfDayDistribution($events),
            'last_seen' => $events->last()->created_at->toIso8601String(),
            'cold' => $events->count() < self::COLD_THRESHOLD,
        ];

        $key = $userId
            ? ['user_id' => $userId]
            : ['anon_id' => $anonId, 'user_id' => null];

        UserSignals::query()->updateOrCreate($key, [
            'anon_id' => $anonId,
            'user_id' => $userId,
            'signals' => $payload,
            'event_count' => $events->count(),
            'rolled_at' => $now,
        ]);
    }

    /**
     * Identities (anon_id, user_id) with at least one event since the
     * cutoff. Null cutoff = everyone (used for catchup).
     *
     * @return array<int, array{anon_id: ?string, user_id: ?int}>
     */
    private function identitiesNeedingRefresh(?Carbon $cutoff): array
    {
        $query = DB::table('user_events')
            ->select('anon_id', 'user_id')
            ->groupBy('anon_id', 'user_id');

        if ($cutoff !== null) {
            $query->where('created_at', '>=', $cutoff);
        }

        return $query->get()
            ->map(static fn ($row) => [
                'anon_id' => $row->anon_id,
                'user_id' => $row->user_id ? (int) $row->user_id : null,
            ])
            ->all();
    }

    /**
     * @return Collection<int, UserEvent>
     */
    private function loadEvents(?string $anonId, ?int $userId): Collection
    {
        $cutoff = Carbon::now()->subDays(self::LOOKBACK_DAYS);

        return UserEvent::query()
            ->where(function ($q) use ($anonId, $userId) {
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
                if ($anonId) {
                    $q->orWhere('anon_id', $anonId);
                }
            })
            ->where('created_at', '>=', $cutoff)
            ->orderBy('created_at')
            ->limit(2000) // hard cap per identity per window
            ->get();
    }

    /**
     * @return array<int, array{id: int, score: float}>
     */
    private function topCategories(Collection $events, Carbon $now): array
    {
        $scores = [];
        foreach ($events as $event) {
            if (! $event->category_id) {
                continue;
            }
            $weight = $this->weightFor($event, $now);
            $scores[$event->category_id] = ($scores[$event->category_id] ?? 0.0) + $weight;
        }

        return $this->topN($scores, fn ($id, $score) => ['id' => (int) $id, 'score' => round($score, 4)]);
    }

    /**
     * @return array<int, array{area: string, score: float}>
     */
    private function topAreas(Collection $events, Carbon $now): array
    {
        $scores = [];
        foreach ($events as $event) {
            if (! $event->user_area) {
                continue;
            }
            $weight = $this->weightFor($event, $now);
            $scores[$event->user_area] = ($scores[$event->user_area] ?? 0.0) + $weight;
        }

        return $this->topN($scores, fn ($area, $score) => ['area' => $area, 'score' => round($score, 4)]);
    }

    /**
     * Brand = parent_id of any business / offering they touched.
     *
     * @return array<int, array{id: int, score: float}>
     */
    private function topBrands(Collection $events, Carbon $now): array
    {
        $businessIds = $events
            ->where('target_type', 'business')
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($businessIds)) {
            return [];
        }

        $idToParent = Business::query()
            ->whereIn('id', $businessIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id', 'id')
            ->all();

        if (empty($idToParent)) {
            return [];
        }

        $scores = [];
        foreach ($events as $event) {
            if ($event->target_type !== 'business' || ! $event->target_id) {
                continue;
            }
            $brand = $idToParent[$event->target_id] ?? null;
            if (! $brand) {
                continue;
            }
            $weight = $this->weightFor($event, $now);
            $scores[$brand] = ($scores[$brand] ?? 0.0) + $weight;
        }

        return $this->topN($scores, fn ($id, $score) => ['id' => (int) $id, 'score' => round($score, 4)]);
    }

    /**
     * Dominant price band of viewed offerings, mapped 1..4. Returns
     * null if no offering signals seen.
     */
    private function dominantPriceRange(Collection $events): ?int
    {
        $offeringIds = $events
            ->where('target_type', 'offering')
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($offeringIds)) {
            return null;
        }

        $offerings = BusinessOffering::query()
            ->whereIn('id', $offeringIds)
            ->select('id', 'price')
            ->get();

        if ($offerings->isEmpty()) {
            return null;
        }

        $median = (float) $offerings->pluck('price')->filter()->median();
        if ($median <= 0) {
            return null;
        }

        // Bangladesh-friendly bands (BDT). Tweak as needed.
        return match (true) {
            $median < 200 => 1,
            $median < 600 => 2,
            $median < 1500 => 3,
            default => 4,
        };
    }

    /**
     * @return array{morning: float, afternoon: float, evening: float, night: float}
     */
    private function timeOfDayDistribution(Collection $events): array
    {
        $buckets = ['morning' => 0, 'afternoon' => 0, 'evening' => 0, 'night' => 0];
        foreach ($events as $event) {
            $hour = (int) $event->created_at->format('H');
            $bucket = match (true) {
                $hour >= 5 && $hour < 12 => 'morning',
                $hour >= 12 && $hour < 17 => 'afternoon',
                $hour >= 17 && $hour < 22 => 'evening',
                default => 'night',
            };
            $buckets[$bucket]++;
        }
        $total = max(1, array_sum($buckets));
        return [
            'morning' => round($buckets['morning'] / $total, 3),
            'afternoon' => round($buckets['afternoon'] / $total, 3),
            'evening' => round($buckets['evening'] / $total, 3),
            'night' => round($buckets['night'] / $total, 3),
        ];
    }

    private function weightFor(UserEvent $event, Carbon $now): float
    {
        $base = self::EVENT_WEIGHTS[$event->event] ?? 0.5;
        $ageDays = $event->created_at->diffInDays($now, true);
        $decay = pow(0.5, $ageDays / self::HALF_LIFE_DAYS);
        return $base * $decay;
    }

    /**
     * Pick top-N keys by score, format with the supplied row mapper.
     *
     * @template T
     * @param  array<int|string, float>  $scores
     * @param  callable(int|string, float): T  $mapRow
     * @return array<int, T>
     */
    private function topN(array $scores, callable $mapRow): array
    {
        if (empty($scores)) {
            return [];
        }
        arsort($scores);
        $sliced = array_slice($scores, 0, self::TOP_N, preserve_keys: true);
        $out = [];
        foreach ($sliced as $key => $score) {
            if ($score <= 0) {
                continue;
            }
            $out[] = $mapRow($key, $score);
        }
        return $out;
    }

    private function parseSince(string $since): Carbon
    {
        $now = Carbon::now();
        return match ($since) {
            '1 hour' => $now->subHour(),
            '6 hours' => $now->subHours(6),
            '1 day' => $now->subDay(),
            '7 days' => $now->subDays(7),
            default => $now->subHour(),
        };
    }
}
