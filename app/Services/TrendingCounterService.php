<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Real-time trending counters backed by Redis sorted sets.
 *
 * Pattern:
 *   trending:{kind}:{period}        → ZSET of {member: score}
 *   trending:{kind}:{period}:{area} → ZSET scoped to a location bucket
 *
 * Where:
 *   kind   = business | offering | category | search_term | attraction
 *   period = day:YYYY-MM-DD | hour:YYYY-MM-DD-HH | week:YYYY-Www | month:YYYY-MM
 *
 * Increment is O(log N); top-N reads via ZREVRANGEBYSCORE are O(log N + M).
 * Each set is bounded with `ZREMRANGEBYRANK` so memory stays flat even
 * with millions of distinct keys per period.
 *
 * Failures never bubble up — analytics must not block product traffic.
 */
class TrendingCounterService
{
    public const KIND_BUSINESS    = 'business';
    public const KIND_OFFERING    = 'offering';
    public const KIND_CATEGORY    = 'category';
    public const KIND_ATTRACTION  = 'attraction';
    public const KIND_SEARCH_TERM = 'search_term';

    public const PERIOD_HOUR  = 'hour';
    public const PERIOD_DAY   = 'day';
    public const PERIOD_WEEK  = 'week';
    public const PERIOD_MONTH = 'month';

    /** Maximum members retained per ZSET. */
    private const MAX_MEMBERS = 5000;

    /** TTL per period (seconds). Longer than the period itself so reads are safe near rollovers. */
    private const TTL = [
        self::PERIOD_HOUR  => 3 * 3600,            // 3 hours
        self::PERIOD_DAY   => 7 * 86400,           // 7 days
        self::PERIOD_WEEK  => 35 * 86400,          // 35 days
        self::PERIOD_MONTH => 13 * 30 * 86400,     // ~13 months
    ];

    /**
     * Increment counters for an item across all standard time buckets.
     *
     * @param  string|int  $itemId  Member key (numeric for models, raw string for search terms)
     */
    public function bump(
        string $kind,
        string|int $itemId,
        ?string $area = null,
        float $weight = 1.0,
    ): void {
        try {
            $now = now();
            $member = (string) $itemId;
            $periods = [
                self::PERIOD_HOUR  => $now->format('Y-m-d-H'),
                self::PERIOD_DAY   => $now->format('Y-m-d'),
                self::PERIOD_WEEK  => $now->format('o-\WW'),
                self::PERIOD_MONTH => $now->format('Y-m'),
            ];

            $client = Redis::connection('default');

            foreach ($periods as $period => $bucket) {
                $globalKey = $this->key($kind, $period, $bucket);
                $client->zincrby($globalKey, $weight, $member);
                $client->expire($globalKey, self::TTL[$period]);
                $client->zremrangebyrank($globalKey, 0, -(self::MAX_MEMBERS + 1));

                if ($area !== null && $area !== '') {
                    $areaKey = $this->key($kind, $period, $bucket, $area);
                    $client->zincrby($areaKey, $weight, $member);
                    $client->expire($areaKey, self::TTL[$period]);
                    $client->zremrangebyrank($areaKey, 0, -(self::MAX_MEMBERS + 1));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TrendingCounter: bump failed', [
                'kind' => $kind,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read top items for a given kind/period.
     *
     * @return array<int, array{member: string, score: float}>
     */
    public function top(
        string $kind,
        string $period = self::PERIOD_DAY,
        int $limit = 20,
        ?string $area = null,
        ?string $bucket = null,
    ): array {
        try {
            $bucket ??= $this->currentBucket($period);
            $key = $this->key($kind, $period, $bucket, $area);

            $rows = Redis::connection('default')
                ->zrevrange($key, 0, max(0, $limit - 1), ['WITHSCORES' => true]);

            $out = [];
            foreach ($rows as $member => $score) {
                $out[] = ['member' => (string) $member, 'score' => (float) $score];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('TrendingCounter: read failed', [
                'kind' => $kind,
                'period' => $period,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function currentBucket(string $period): string
    {
        return match ($period) {
            self::PERIOD_HOUR  => now()->format('Y-m-d-H'),
            self::PERIOD_DAY   => now()->format('Y-m-d'),
            self::PERIOD_WEEK  => now()->format('o-\WW'),
            self::PERIOD_MONTH => now()->format('Y-m'),
            default            => now()->format('Y-m-d'),
        };
    }

    private function key(string $kind, string $period, string $bucket, ?string $area = null): string
    {
        $segments = ['trending', $kind, $period, $bucket];
        if ($area !== null && $area !== '') {
            $segments[] = 'area:'.$this->normalizeArea($area);
        }

        return implode(':', $segments);
    }

    /**
     * Lower-cased, alnum-only area key so "Dhanmondi Ward 2" and
     * "dhanmondi-ward-2" land in the same bucket.
     */
    private function normalizeArea(string $area): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '_', $area) ?? $area;

        return mb_strtolower(trim($slug, '_'));
    }
}
